(function (root, factory) {
    const api = factory();

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }

    root.Thai2D3DSaleEntryKeyboard = api;
})(globalThis, function () {
    function normalizeTypedText(text) {
        return String(text)
            .replace(/\*/g, 'R')
            .replace(/[a-z]/g, character => character.toUpperCase());
    }

    function normalizeKeyInput(text, code) {
        return code === 'NumpadDecimal' ? '000' : normalizeTypedText(text);
    }

    return Object.freeze({ normalizeTypedText, normalizeKeyInput });
});
