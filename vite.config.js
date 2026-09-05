import { cpSync, existsSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

/*
 | مشغّل H5P يُنسَخ إلى public/ لا يُحزَم مع تطبيقنا.
 |
 | المشغّل يُحمّل مكتباته وخطوطه بمساراتٍ نسبية وقت التشغيل، ويفتح
 | المحتوى داخل إطارٍ مستقلّ. فحزمُه مع app.js يكسر تلك المسارات،
 | والنسخُ يُبقيها كما يتوقّعها.
 */
function h5pPlayer() {
    return {
        name: 'copy-h5p-standalone',
        buildStart() {
            const from = 'node_modules/h5p-standalone/dist';

            if (existsSync(from)) {
                cpSync(from, 'public/vendor/h5p', { recursive: true });
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Tajawal', { weights: [400, 500, 700, 800] }),
                bunny('IBM Plex Mono', { weights: [400, 500] }),
            ],
        }),
        tailwindcss(),
        h5pPlayer(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
