import {
  scopedPreflightStyles,
  isolateInsideOfContainer, // there are also isolateOutsideOfContainer and isolateForComponents
} from 'tailwindcss-scoped-preflight';

/** @type {import("tailwindcss").Config} */
const config = {
  theme: {
    extend: {
      colors: {
        'primary-color': '#F29F2C'
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