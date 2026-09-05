/*
 | مشغّل المحتوى التفاعلي (H5P).
 |
 | ## لماذا مشغّل في المتصفّح لا خادم H5P
 |
 | خادم H5P الرسمي يحتفظ بمكتبةٍ مركزية تُثبَّت فيها أنواع المحتوى،
 | ويُرقّيها ويُهجّر محتواها. وذلك في منصّةٍ متعدّدة المستأجرين يعني
 | أن ترقيةً لمشترِكٍ تكسر محتوى آخر.
 |
 | وحزمة `.h5p` تحمل مكتباتها معها. فيكفي فكُّها وتشغيلها من
 | مجلّدها: كلّ مشترِكٍ بنسخته، ولا ترقية تكسر أحداً.
 |
 | ## والنتائج تُرسَل إلى خادمنا
 |
 | المحتوى يُبلّغ عن نفسه بعبارات xAPI عبر `H5P.externalDispatcher`.
 | فنستمع إليها ونرسلها — والخادم يتجاهل ما فيها من اسم الفاعل
 | ويكتب صاحب الجلسة.
 */

const loaded = new Set();

function loadScript(src) {
    if (loaded.has(src)) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const el = document.createElement('script');
        el.src = src;
        el.onload = () => {
            loaded.add(src);
            resolve();
        };
        el.onerror = () => reject(new Error(src));
        document.head.appendChild(el);
    });
}

async function boot(root) {
    const folder = root.dataset.h5pFolder;
    const base = root.dataset.h5pBase;
    const url = root.dataset.h5pUrl;
    const token = root.dataset.h5pToken;

    if (!folder || !base) {
        return;
    }

    const target = root.querySelector('[data-h5p-frame]') || root;

    try {
        await loadScript(`${base}/main.bundle.js`);

        // eslint-disable-next-line no-undef
        await new H5PStandalone.H5P(target, {
            h5pJsonPath: folder,
            frameJs: `${base}/frame.bundle.js`,
            frameCss: `${base}/styles/h5p.css`,
            // الشعار والتضمين والحقوق: لا تعني الطالب، وتزحم الشريط
            export: false,
            embed: false,
            copyright: false,
            icon: false,
            frame: true,
        });
    } catch (error) {
        const note = root.querySelector('[data-h5p-error]');

        if (note) {
            note.hidden = false;
        }

        return;
    }

    if (!url || !token) {
        return;
    }

    /*
     | المستمع على مُرسِل الإطار لا على مُرسِل الصفحة.
     |
     | H5P يُشغّل المحتوى داخل إطارٍ مستقلّ، وله فيه نسخته من
     | `externalDispatcher` غير نسخة الصفحة — تحقّقنا من ذلك حيّاً:
     | `parent.H5P.externalDispatcher !== frame.H5P.externalDispatcher`.
     | فالاستماع إلى الصفحة وحدها يجعل النتائج لا تصل أبداً، بلا خطأ
     | يظهر في أي مكان.
     |
     | ويُستمَع إلى الاثنين: بعض أنواع المحتوى تُدمَج في الصفحة بلا
     | إطار (embedType: div)، فمُرسِلها هو مُرسِل الصفحة.
     */
    /*
     | العبارة الواحدة تصل مرّتين.
     |
     | H5P يُطلق الحدث في مُرسِل الإطار ثم يمرّره إلى مُرسِل الصفحة،
     | ونحن مستمعون إلى الاثنين — فتُسجَّل نتيجةٌ واحدة صفّين. رأيناه
     | حيّاً: عبارتان بالثانية نفسها والدرجة نفسها.
     |
     | فتُبصَم العبارة بفعلها وهدفها ودرجتها، ولا تُرسَل بصمةٌ مرّتين
     | في الثانيتين. والفاصل قصير عمداً: طالبٌ يعيد المحاولة بعد
     | دقيقة نتيجتُه نتيجةٌ ثانية لا تكرار.
     */
    const seen = new Map();

    const send = (event) => {
        const statement = event?.data?.statement;

        if (!statement?.verb?.id) {
            return;
        }

        const print = [
            statement.verb.id,
            statement.object?.id ?? '',
            statement.result?.score?.raw ?? '',
            statement.result?.completion ?? '',
        ].join('|');

        const now = Date.now();

        if (seen.has(print) && now - seen.get(print) < 2000) {
            return;
        }

        seen.set(print, now);

        /*
         | لا يُرسَل إلا ما يعني تقدّماً.
         |
         | المحتوى يُبلّغ عن كل ضغطةٍ ونقلةِ شريحة، وإرسالُ الكلّ
         | يملأ الجدول بما لا يُقرأ ويُبطئ التقارير.
         */
        const verb = statement.verb.id.split('/').pop();

        if (!['completed', 'answered', 'passed', 'failed'].includes(verb)) {
            return;
        }

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(statement),
            keepalive: true,
        }).catch(() => {});
    };

    const attached = new Set();

    const attach = () => {
        const frame = root.querySelector('iframe.h5p-iframe');

        for (const scope of [window, frame && frame.contentWindow]) {
            const dispatcher = scope && scope.H5P && scope.H5P.externalDispatcher;

            if (dispatcher && !attached.has(dispatcher)) {
                attached.add(dispatcher);
                dispatcher.on('xAPI', send);
            }
        }

        return attached.size > 0;
    };

    /*
     | ويُحاوَل مرّاتٍ لا مرّة.
     |
     | H5P داخل الإطار يُنشأ بعد أن ينتهي وعدُ التشغيل، فمحاولةٌ
     | واحدة عند الانتهاء تسبقه أحياناً — والفرق ثوانٍ لا تُضبط
     | بالحدس.
     */
    for (let tries = 0; tries < 40; tries++) {
        if (attach() && tries > 4) {
            break;
        }

        await new Promise((resolve) => setTimeout(resolve, 250));
    }
}

export function startH5pPlayers() {
    document.querySelectorAll('[data-h5p-folder]').forEach((root) => {
        if (root.dataset.h5pBooted === '1') {
            return;
        }

        root.dataset.h5pBooted = '1';
        boot(root);
    });
}
