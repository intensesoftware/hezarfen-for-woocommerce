import {
  scopedPreflightStyles,
  isolateInsideOfContainer, // there are also isolateOutsideOfContainer and isolateForComponents
} from 'tailwindcss-scoped-preflight';

/** @type {import("tailwindcss").Config} */
const config = {
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