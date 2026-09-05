/**
 * مفاتيح المرور في المتصفّح.
 *
 * ## الترميز هو نصف العمل
 *
 * الخادم يتكلّم Base64URL، والمتصفّح يتكلّم `ArrayBuffer`. وكلُّ
 * أعطال WebAuthn تقريباً سببُها تحويلٌ ناقص بينهما — ولا رسالةَ خطأ
 * مفيدة: يفشل التوقيع بلا سبب.
 *
 * ## و`+` و`/` ليست Base64URL
 *
 * المعيار يستعمل `-` و`_` بلا حشو. وإرسالُ Base64 عادية يجعل
 * المعرّف لا يُطابَق عند الخادم، فيقال «مفتاح غير مسجَّل» عن مفتاحٍ
 * سُجّل للتوّ.
 */

function fromBase64Url(value) {
    const padded = value.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(padded + '='.repeat((4 - (padded.length % 4)) % 4));
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);

    return bytes.buffer;
}

function toBase64Url(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';

    for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);

    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

/** يحوّل ما يعطيه الخادم إلى ما يفهمه `navigator.credentials` */
function decodeOptions(options) {
    const decoded = { ...options };

    decoded.challenge = fromBase64Url(options.challenge);

    if (options.user) {
        decoded.user = { ...options.user, id: fromBase64Url(options.user.id) };
    }

    ['excludeCredentials', 'allowCredentials'].forEach((key) => {
        if (Array.isArray(options[key])) {
            decoded[key] = options[key].map((c) => ({ ...c, id: fromBase64Url(c.id) }));
        }
    });

    return decoded;
}

/** ويحوّل ما يعطيه الجهاز إلى ما يقرؤه الخادم */
function encodeCredential(credential) {
    const response = {
        id: credential.id,
        rawId: toBase64Url(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: toBase64Url(credential.response.clientDataJSON),
        },
    };

    if (credential.response.attestationObject) {
        response.response.attestationObject = toBase64Url(credential.response.attestationObject);
    }

    if (credential.response.authenticatorData) {
        response.response.authenticatorData = toBase64Url(credential.response.authenticatorData);
        response.response.signature = toBase64Url(credential.response.signature);
        response.response.userHandle = credential.response.userHandle
            ? toBase64Url(credential.response.userHandle)
            : null;
    }

    return response;
}

/** هل يدعم هذا المتصفّح المفاتيح أصلاً؟ */
export function passkeysSupported() {
    return typeof window.PublicKeyCredential !== 'undefined'
        && typeof navigator.credentials?.create === 'function';
}

async function json(url, options = {}) {
    const response = await fetch(url, {
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            ...(options.headers ?? {}),
        },
        ...options,
    });

    const body = await response.json().catch(() => ({}));

    if (! response.ok) throw new Error(body.message || 'تعذّرت العملية.');

    return body;
}

/** يسجّل مفتاحاً جديداً لمن دخل بالفعل */
export async function registerPasskey(label) {
    const options = await json(window.usosPasskeys.optionsUrl);

    const credential = await navigator.credentials.create({
        publicKey: decodeOptions(options),
    });

    if (! credential) throw new Error('أُلغي التسجيل.');

    return json(window.usosPasskeys.registerUrl, {
        method: 'POST',
        headers: { 'X-Passkey-Label': encodeURIComponent(label || '') },
        body: JSON.stringify(encodeCredential(credential)),
    });
}

/** يدخل بمفتاح — بلا بريدٍ ولا كلمة مرور */
export async function loginWithPasskey() {
    const options = await json(window.usosPasskeys.loginOptionsUrl);

    const credential = await navigator.credentials.get({
        publicKey: decodeOptions(options),
    });

    if (! credential) throw new Error('أُلغي الدخول.');

    const result = await json(window.usosPasskeys.loginUrl, {
        method: 'POST',
        body: JSON.stringify(encodeCredential(credential)),
    });

    window.location.href = result.redirect;
}
