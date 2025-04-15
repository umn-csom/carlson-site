module.exports = {
  plugins: [
    require('postcss-prefixwrap')('.ck-content', {
      ignoreSelectors: [
        'body',
        'html',
        ':root',
        '.ck-content',
        '.ck.ck-content',
      ]
    }),
    require('postcss-selector-replace')({
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
    require('postcss-rem-to-pixel')({
      rootValue: 10,
      propList: ["*"],
      mediaQuery: true,
    })
  ]
}
