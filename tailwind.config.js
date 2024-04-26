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
        'gray-2': '#E4E4E4'
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