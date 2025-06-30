// Trigger Tablesaw to convert tables after an AJAX call (such as Views tables
// loaded via AJAX from a pager or exposed filter. See #3137956
// This was rewritten from jQuery into JavaScript in 2024 to avoid the jQuery
// dependency.
(function() {
    const send = XMLHttpRequest.prototype.send
    XMLHttpRequest.prototype.send = function() {
        this.addEventListener('load', function() {
            Tablesaw.init();
        })
        return send.apply(this, arguments)
    }
})()
