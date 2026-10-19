/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./src/Resources/views/shop/**/*.blade.php",
        "./src/Resources/views/admin/**/*.blade.php",
    ],

    theme: {
        screens: {
            sm: "525px",
            md: "768px",
            lg: "1024px",
            xl: "1240px",
            "2xl": "1440px",
            1920: "1920px",
            1180: "1180px",
            1060: "1060px",
            991: "991px",
            868: "868px",
        },

        extend: {},
    },

    plugins: [],

    corePlugins: {
        preflight: false,
    },
};
