/*
 | نقاط التفاعل داخل الفيديو.
 |
 | الطالب يشاهد عشرين دقيقة ثم ينتقل، ولا يعرف المدرّس أفَهِم أم
 | مرّت الصورة أمامه. والسؤال في منتصف الفيديو يكشف ذلك في ثانيته.
 |
 | ## التقديم يُمنع، والرجوع لا يُمنع أبداً
 |
 | النقطة الإلزامية تمنع تخطّيها إلى الأمام حتى تُجاب. أمّا الرجوع
 | فمسموح دائماً: من لم يفهم يعيد الشرح، ومنعُه من الإعادة يحوّل
 | التفاعل إلى عقوبة — والغرض أن يفهم لا أن يُحبَس.
 */

export function initVideoMoments(root) {
    const video = root.querySelector('video');
    const panel = root.querySelector('[data-moment-panel]');

    if (!video || !panel) return;

    let moments = [];

    try {
        moments = JSON.parse(root.dataset.moments || '[]');
    } catch {
        return;
    }

    if (moments.length === 0) return;

    const token = root.dataset.momentToken;
    const answered = new Set(moments.filter((m) => m.answered).map((m) => m.id));

    // أبعد نقطة إلزامية أُجيبت: ما بعدها ممنوع حتى تُجاب
    let gate = Infinity;

    const nextGate = () => {
        const pending = moments.filter((m) => m.required && !answered.has(m.id));

        gate = pending.length === 0 ? Infinity : Math.min(...pending.map((m) => m.at));
    };

    nextGate();

    let showing = null;

    const show = (moment) => {
        showing = moment;
        video.pause();

        panel.hidden = false;
        panel.querySelector('[data-moment-body]').innerHTML = moment.html;
        panel.querySelector('[data-moment-result]').hidden = true;

        /*
         | الخيارات تُرسَم أزراراً، وما لا خيارات له يبقى خانةَ كتابة.
         |
         | كان السؤال يظهر بنصّه وحده وتحته خانة كتابة — فسؤال
         | «اختيار واحد» بلا خياراته، والطالب لا يعرف أن عليه كتابة
         | حرف الخيار.
         */
        const choices = panel.querySelector('[data-moment-choices]');
        const input = panel.querySelector('[data-moment-answer]');
        const options = moment.options || {};
        const keys = Object.keys(options);

        if (choices) {
            choices.innerHTML = '';
            choices.hidden = keys.length === 0;
        }

        if (input) {
            input.value = '';
            input.closest('[data-moment-text]')?.toggleAttribute('hidden', keys.length > 0);
        }

        if (keys.length > 0 && choices) {
            for (const key of keys) {
                const label = document.createElement('label');
                label.className = 'flex items-center gap-2.5 text-sm cursor-pointer py-1 min-h-11';

                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'moment-choice';
                radio.value = key;
                radio.className = 'size-5 shrink-0 accent-[var(--color-primary)] rounded-full';

                const text = document.createElement('span');
                text.className = 'min-w-0';
                text.textContent = options[key];

                label.append(radio, text);
                choices.append(label);
            }
        } else if (input) {
            input.focus();
        }

        panel.querySelector('[data-moment-form]').hidden = moment.kind !== 'question';
        panel.querySelector('[data-moment-skip]').hidden = moment.required;
    };

    const close = () => {
        showing = null;
        panel.hidden = true;
        video.play().catch(() => {});
    };

    video.addEventListener('timeupdate', () => {
        if (showing) return;

        const t = Math.floor(video.currentTime);

        /*
         | البوابة تُفحص أولاً.
         |
         | السحب إلى ما بعد نقطة إلزامية لم تُجَب يُرَدّ إليها بدل
         | أن يُمنع السحب كلّه — فالمنع الصامت يبدو عطلاً.
         */
        if (t > gate + 1) {
            video.currentTime = gate;

            return;
        }

        const due = moments.find((m) => !answered.has(m.id) && t >= m.at && t < m.at + 2);

        if (due) show(due);
    });

    panel.querySelector('[data-moment-skip]')?.addEventListener('click', () => {
        if (showing) answered.add(showing.id);
        close();
    });

    panel.querySelector('[data-moment-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!showing) return;

        const picked = panel.querySelector('[data-moment-choices] input:checked');
        const answer = picked
            ? picked.value
            : panel.querySelector('[data-moment-answer]').value.trim();

        if (answer === '') return;

        const res = await fetch(root.dataset.momentUrl.replace('__ID__', String(showing.id)), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            body: JSON.stringify({ answer }),
        }).then((r) => r.json()).catch(() => null);

        if (!res) return;

        const result = panel.querySelector('[data-moment-result]');
        result.hidden = false;
        result.className = res.correct === false ? 'text-sm text-danger' : 'text-sm text-success';
        result.textContent = res.correct === false
            ? (res.expected ? `الإجابة الصحيحة: ${res.expected}` : 'إجابة غير صحيحة')
            : 'إجابة صحيحة';

        answered.add(showing.id);
        nextGate();

        // مهلةٌ ليقرأ الصواب قبل أن يمضي الفيديو
        setTimeout(close, res.correct === false ? 3500 : 1200);
    });
}
