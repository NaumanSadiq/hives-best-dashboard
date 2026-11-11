export function clipboardCapability(scope = window) {
    if (!scope.isSecureContext) return {available: false, reason: 'Image copying requires trusted HTTPS on hives-best-dashboard.local. Open the HTTPS address after the local certificate and Apache setup is complete. Download PNG works here.'};
    if (!scope.navigator.clipboard?.write || !scope.ClipboardItem || (scope.ClipboardItem.supports && !scope.ClipboardItem.supports('image/png'))) return {available: false, reason: 'This browser does not support PNG copying. Use current Chrome or Edge, or Download PNG.'};
    return {available: true, reason: ''};
}

export function clipboardFailure(error) {
    if (['NotAllowedError', 'SecurityError'].includes(error?.name)) return 'The browser blocked clipboard access. Keep this invoice tab active, allow Clipboard in this site’s browser permissions, then click Copy Invoice Image again. Download PNG remains available.';
    return 'The image could not be copied. Try Copy Invoice Image again in an active Chrome or Edge tab, or use Download PNG.';
}
