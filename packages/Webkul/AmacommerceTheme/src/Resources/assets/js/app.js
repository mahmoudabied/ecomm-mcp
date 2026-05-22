import '../css/app.css';

import.meta.glob(["../images/**"]);

import { createApp } from "vue/dist/vue.esm-bundler";

import Axios from "./plugins/axios";
import Emitter from "./plugins/emitter";
import Shop from "./plugins/shop";
import VeeValidate from "./plugins/vee-validate";
import Flatpickr from "./plugins/flatpickr";
import Debounce from "./directives/debounce";

const vueApp = createApp({
    data() {
        return {};
    },

    mounted() {
        this.lazyImages();
    },

    methods: {
        onSubmit() {},

        onInvalidSubmit() {},

        lazyImages() {
            var lazyImages = [].slice.call(document.querySelectorAll('img.lazy'));

            let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        let lazyImage = entry.target;

                        lazyImage.src = lazyImage.dataset.src;

                        lazyImage.classList.remove('lazy');

                        lazyImageObserver.unobserve(lazyImage);
                    }
                });
            });

            lazyImages.forEach(function(lazyImage) {
                lazyImageObserver.observe(lazyImage);
            });
        },
    },
});

[Axios, Emitter, Shop, VeeValidate, Flatpickr].forEach((plugin) => vueApp.use(plugin));

vueApp.directive("debounce", Debounce);

if (window._vueAppQueue) {
    window._vueAppQueue.plugins.forEach(args => vueApp.use(...args));
    window._vueAppQueue.components.forEach(args => vueApp.component(...args));
    window._vueAppQueue.directives.forEach(args => vueApp.directive(...args));
}

window.app = vueApp;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => vueApp.mount('#app'), 0);
    });
} else {
    setTimeout(() => vueApp.mount('#app'), 0);
}
