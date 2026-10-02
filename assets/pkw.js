(function () {
  'use strict';
  var f = document.getElementById('pkw-txform');
  if (f) {
    var sync = function () {
      var r = f.querySelector('input[name=jenis]:checked'); var j = r ? r.value : 'masuk';
      f.querySelectorAll('.pkw-only-kat').forEach(function (e) { e.hidden = j === 'pindah'; });
      f.querySelectorAll('.pkw-only-pindah').forEach(function (e) { e.hidden = j !== 'pindah'; });
      var kat = document.getElementById('pkw-kat');
      if (kat) {
        kat.required = j !== 'pindah';
        kat.querySelectorAll('optgroup').forEach(function (g) {
          var on = g.getAttribute('data-jenis') === j; g.hidden = !on; g.disabled = !on;
        });
        var sel = kat.options[kat.selectedIndex];
        if (sel && sel.parentNode.tagName === 'OPTGROUP' && sel.parentNode.disabled) { kat.value = ''; }
      }
      var la = f.querySelector('.pkw-lbl-akaun'); if (la) { la.textContent = j === 'masuk' ? 'Dimasukkan ke akaun' : (j === 'keluar' ? 'Dibayar dari akaun' : 'Dari akaun'); }
      var lp = f.querySelector('.pkw-lbl-pihak'); if (lp) { lp.textContent = j === 'masuk' ? 'Diterima daripada' : (j === 'keluar' ? 'Dibayar kepada' : 'Catatan pihak'); }
    };
    f.addEventListener('change', function (e) { if (e.target.name === 'jenis') { sync(); } });
    sync();
  }
  var t = document.getElementById('pkw-tempoh');
  if (t) {
    var tf = function () { document.querySelectorAll('.pkw-t').forEach(function (e) { e.hidden = !e.classList.contains('pkw-t-' + t.value); }); };
    t.addEventListener('change', tf); tf();
  }
  var p = document.getElementById('pkw-print');
  if (p) { p.addEventListener('click', function () { window.print(); }); }
})();
