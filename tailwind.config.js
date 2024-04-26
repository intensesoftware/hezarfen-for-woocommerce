import {
  scopedPreflightStyles,
  isolateInsideOfContainer, // there are also isolateOutsideOfContainer and isolateForComponents
} from 'tailwindcss-scoped-preflight';

/** @type {import("tailwindcss").Config} */
const config = {
  theme: {
    extend: {
      colors: {
        'primary-color': '#F29F2C',
        'gray-1': '#A0A0A0',
        'gray-2': '#E4E4E4',
        'gray-3': '#BEBEBE',
        'orange-2': '#FFC16B',
        'orange-1': '#FFE6C2',
      },
      screens: {
        'sm': '640px', // Original size
        'md': '768px', // Original size
        'lg': '1024px', // Original size
        'xl': '1280px', // Original size
        '2xl': '1536px', // Original size
        '3xl': '1792px', // New custom breakpoint
        '4xl': '2048px', // New custom breakpoint
        '5xl': '2304px', // New custom breakpoint
        '6xl': '2560px', // New custom breakpoint
      },
    }
  },
  content: [
    './packages/manual-shipment-tracking/templates/order-edit/metabox-shipment.php',
    "./node_modules/flowbite/**/*.js"
  ],
  plugins: [
    require('flowbite/plugin'),
    scopedPreflightStyles({
      isolationStrategy: isolateInsideOfContainer('.hez-ui'),
    }),
  ]
};

exports.default = config;