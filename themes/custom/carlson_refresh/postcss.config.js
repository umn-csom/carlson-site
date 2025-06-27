import prefixwrap from 'postcss-prefixwrap';
import selectorReplace from 'postcss-selector-replace';
import remToPixel from 'postcss-rem-to-pixel';

export default {
  plugins: [
    prefixwrap('.ck-content', {
      ignoreSelectors: [
        'body',
        'html',
        ':root',
        '.ck-content',
        '.ck.ck-content',
      ]
    }),
    selectorReplace({
      before: [
        "body",
        "html",
        ":root",
      ],
      after: [
        ".ck-content",
        ".ck-content",
        '.ck-content',
      ],
    }),
    remToPixel({
      rootValue: 10,
      propList: ["*"],
      mediaQuery: true,
    })
  ]
}
