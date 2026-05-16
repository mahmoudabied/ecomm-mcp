import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig(({ mode }) => {
    const envDir = "../../../";

    Object.assign(process.env, loadEnv(mode, envDir));

    return {
        build: {
            emptyOutDir: true,
        },
        envDir,
        server: {
            host: process.env.VITE_HOST || "localhost",
            port: process.env.VITE_PORT || 5173,
        },
        plugins: [
            laravel({
                hotFile: "../../../public/amacommerce-theme-vite.hot",
                publicDirectory: "../../../public",
                buildDirectory: "themes/shop/amacommerce/build",
                input: [
                    "src/Resources/assets/css/app.css",
                    "src/Resources/assets/js/app.js",
                    "src/Resources/assets/images/favicon.ico",
                    "src/Resources/assets/images/logo.svg",
                    "src/Resources/assets/images/hero/iphone-banner.png",
                    "src/Resources/assets/images/promo/jbl-speaker.png",
                    "src/Resources/assets/images/arrivals/ps5.png",
                    "src/Resources/assets/images/arrivals/womens-collection.png",
                    "src/Resources/assets/images/arrivals/speakers.png",
                    "src/Resources/assets/images/arrivals/perfume.png",
                    "src/Resources/assets/images/categories/phone.svg",
                    "src/Resources/assets/images/categories/computer.svg",
                    "src/Resources/assets/images/categories/smartwatch.svg",
                    "src/Resources/assets/images/categories/camera.svg",
                    "src/Resources/assets/images/categories/headphones.svg",
                    "src/Resources/assets/images/categories/gaming.svg",
                    "src/Resources/assets/images/services/delivery.svg",
                    "src/Resources/assets/images/services/customer-service.svg",
                    "src/Resources/assets/images/services/money-back.svg",
                    "src/Resources/assets/images/auth/shopping-side.png",
                    "src/Resources/assets/images/default-language.svg",
                ],
                refresh: true,
            }),
        ],
    };
});
