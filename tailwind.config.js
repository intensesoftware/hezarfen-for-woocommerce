import {
  scopedPreflightStyles,
  isolateInsideOfContainer, // there are also isolateOutsideOfContainer and isolateForComponents
} from 'tailwindcss-scoped-preflight';

/** @type {import("tailwindcss").Config} */
const config = {
  theme: {
    extend: {
      colors: {
        'text-blue-600': '#000',
        'border-blue-600': '#F29F2C',
        'gray': '#969696'
      }
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