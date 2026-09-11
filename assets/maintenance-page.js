(function () {
    var cd = document.getElementById('maint-cd');
    if (!cd) return;
    var dateAttr = cd.getAttribute('data-target');
    if (!dateAttr) return;

    var target = new Date(dateAttr).getTime();
    var $days    = document.getElementById('cd-days');
    var $hours   = document.getElementById('cd-hours');
    var $minutes = document.getElementById('cd-minutes');
    var $seconds = document.getElementById('cd-seconds');

    function pad(n) { return String(n).padStart(2, '0'); }

    function tick() {
        var diff = target - new Date().getTime();
        if (diff <= 0) {
            cd.innerHTML = '<div class="cd-num">&#10003;</div>';
            return;
        }
        var d = Math.floor(diff / 86400000);
        var h = Math.floor((diff % 86400000) / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        if ($days)    $days.textContent    = pad(d);
        if ($hours)   $hours.textContent   = pad(h);
        if ($minutes) $minutes.textContent = pad(m);
        if ($seconds) $seconds.textContent = pad(s);
    }

    tick();
    setInterval(tick, 1000);
}());
