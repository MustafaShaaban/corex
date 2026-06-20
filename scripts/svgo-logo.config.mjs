// svgo config for the CoreX production logo package.
// Preserves viewBox, accessibility metadata (title/role/aria-label) and rounds
// coordinates so the extracted font outlines stay compact but faithful.
export default {
  multipass: true,
  js2svg: { indent: 2, pretty: false },
  plugins: [
    {
      name: 'preset-default',
      params: {
        overrides: {
          removeViewBox: false,
          removeTitle: false,
          // Keep role/aria-label on the root <svg>.
          removeUnknownsAndDefaults: { keepAriaAttrs: true, keepRoleAttr: true },
          cleanupNumericValues: { floatPrecision: 2 },
          convertPathData: { floatPrecision: 2 },
          mergePaths: false,
        },
      },
    },
  ],
};
