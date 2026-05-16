/** @type {import('tailwindcss').Config} */
export default {
    content: ["./src/Resources/**/*.blade.php", "./src/Resources/**/*.js"],
    theme: {
        extend: {
            colors: {
                primary: '#DB4444',
                'primary-hover': '#E07575',
                secondary: '#00FF66',
                'bg': '#FFFFFF',
                'bg-secondary': '#F5F5F5',
                'text1': '#FAFAFA',
                'text2': '#000000',
                'text-secondary': '#7D8184',
                'rating': '#FFAD33',
                'border-color': 'rgba(0,0,0,0.1)',
            },
            fontFamily: {
                'poppins': ['Poppins', 'sans-serif'],
            },
            maxWidth: {
                'content': '1170px',
            },
            screens: {
                '1440': '1440px',
            },
        },
    },
    plugins: [],
};
