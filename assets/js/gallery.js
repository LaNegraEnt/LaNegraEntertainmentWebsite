/* Galerij: lightbox met pijltjes, toetsenbord en swipe. */
(function () {
  var grid = document.getElementById('gGrid'), box = document.getElementById('gBox'), img = document.getElementById('gImg');
  if (!grid || !box) return;
  var cur = 0, last = null;
  function list() { return Array.prototype.slice.call(grid.querySelectorAll('.g-card img')); }
  function show(i) {
    var l = list(); if (!l.length) return;
    cur = (i + l.length) % l.length;
    img.src = l[cur].currentSrc || l[cur].src; img.alt = l[cur].alt;
  }
  function open(i) { last = document.activeElement; show(i); box.hidden = false; document.body.style.overflow = 'hidden'; box.querySelector('.g-close').focus(); }
  function close() { box.hidden = true; document.body.style.overflow = ''; if (last) last.focus(); }
  grid.addEventListener('click', function (e) {
    var b = e.target.closest('.g-card'); if (!b) return;
    open(list().indexOf(b.querySelector('img')));
  });
  box.querySelector('.g-close').addEventListener('click', close);
  box.querySelector('.g-prev').addEventListener('click', function () { show(cur - 1); });
  box.querySelector('.g-next').addEventListener('click', function () { show(cur + 1); });
  box.addEventListener('click', function (e) { if (e.target === box) close(); });
  document.addEventListener('keydown', function (e) {
    if (box.hidden) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowRight') show(cur + 1);
    if (e.key === 'ArrowLeft') show(cur - 1);
  });
  var x0 = null;
  box.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
  box.addEventListener('touchend', function (e) {
    if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; x0 = null;
    if (Math.abs(dx) > 40) show(cur + (dx < 0 ? 1 : -1));
  });
})();
