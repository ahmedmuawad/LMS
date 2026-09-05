/*
 | جسر SCORM: يُقدَّم للحزمة داخل الإطار.
 |
 | حزمة SCORM تبحث في نوافذ الأب عن كائن اسمه `API` (لـ1.2) أو
 | `API_1484_11` (لـ2004)، فإن لم تجده لم تعمل أصلاً. فنضعه هنا
 | ونترجم نداءاته إلى حفظٍ على خادمنا.
 |
 | ## لماذا نُنفّذه بأنفسنا
 |
 | المكتبات الجاهزة تحمل المعيار كاملاً بمئات المفاتيح، وتسعة
 | أعشارها لا تُقرأ. وما يحتاجه المدرّس أربعة: أتمّ الطالب؟ كم
 | درجته؟ أين توقّف؟ كم قضى؟ — والباقي يُحفَظ كما هو للاستئناف
 | بلا أن نفهمه.
 |
 | ## والحفظ عند `Commit` لا عند كل `SetValue`
 |
 | الحزمة تنادي `SetValue` عشرات المرات في الثانية أثناء التنقّل،
 | وطلبٌ لكل نداء يُغرق الخادم ويُبطئ الحزمة. والمعيار نفسه يجعل
 | `Commit` هي لحظة الحفظ.
 */

const OK = 'true';
const FAIL = 'false';

function createApi(root, version) {
    const url = root.dataset.scormUrl;
    const token = root.dataset.scormToken;

    let cmi = {};

    try {
        cmi = JSON.parse(root.dataset.scormState || '{}');
    } catch {
        cmi = {};
    }

    let dirty = false;
    let started = Date.now();
    let error = '0';

    const statusKey = version === '2004' ? 'cmi.completion_status' : 'cmi.core.lesson_status';
    const scoreKey = version === '2004' ? 'cmi.score.raw' : 'cmi.core.score.raw';
    const locationKey = version === '2004' ? 'cmi.location' : 'cmi.core.lesson_location';

    const save = (final = false) => {
        if (!dirty && !final) return OK;

        const seconds = Math.floor((Date.now() - started) / 1000);
        started = Date.now();

        const body = JSON.stringify({
            cmi,
            lesson_status: cmi[statusKey] || 'incomplete',
            score_raw: cmi[scoreKey] ?? null,
            location: cmi[locationKey] ?? null,
            suspend_data: cmi['cmi.suspend_data'] ?? null,
            seconds,
        });

        /*
         | `sendBeacon` عند الإغلاق و`fetch` أثناء العمل.
         |
         | الإغلاق يُلغي `fetch` فتضيع آخر حالة — وهي أهمّها، لأنها
         | التي يستأنف منها الطالب.
         */
        if (final && navigator.sendBeacon) {
            navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));
        } else {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body,
                keepalive: true,
            }).catch(() => {});
        }

        dirty = false;

        return OK;
    };

    const api = {
        Initialize: () => { started = Date.now(); error = '0'; return OK; },
        Terminate: () => save(true),
        GetValue: (key) => {
            error = '0';

            return cmi[key] ?? '';
        },
        SetValue: (key, value) => {
            cmi[key] = String(value);
            dirty = true;
            error = '0';

            return OK;
        },
        Commit: () => save(),
        GetLastError: () => error,
        GetErrorString: () => '',
        GetDiagnostic: () => '',
    };

    // أسماء 1.2 تختلف عن 2004 وإن تطابق المعنى
    const legacy = {
        LMSInitialize: api.Initialize,
        LMSFinish: api.Terminate,
        LMSGetValue: api.GetValue,
        LMSSetValue: api.SetValue,
        LMSCommit: api.Commit,
        LMSGetLastError: api.GetLastError,
        LMSGetErrorString: api.GetErrorString,
        LMSGetDiagnostic: api.GetDiagnostic,
    };

    window.addEventListener('beforeunload', () => save(true));

    return version === '2004' ? api : { ...api, ...legacy };
}

export function initScorm(root) {
    if (!root.dataset.scormUrl) return;

    const version = root.dataset.scormVersion === '2004' ? '2004' : '1.2';
    const api = createApi(root, version);

    /*
     | الاسم يُوضع على `window` لا على الإطار.
     |
     | الحزمة تصعد في `window.parent` حتى تجد الكائن، فوضعُه هنا
     | يجعلها تجده مهما عمُق تداخل إطاراتها الداخلية.
     */
    if (version === '2004') {
        window.API_1484_11 = api;
    } else {
        window.API = api;
    }
}
