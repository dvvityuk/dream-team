const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
const motionShell = document.querySelector('.shell');
const readingProgress = document.querySelector('.reading-progress');

function updateReadingProgress() {
  const root = document.scrollingElement;
  const remaining = root.scrollHeight - root.clientHeight;
  const progress = remaining > 0 ? Math.min(1, Math.max(0, root.scrollTop / remaining)) : 0;
  readingProgress.style.transform = 'scaleX(' + progress + ')';
}

let progressFrame = 0;
function scheduleReadingProgress() {
  if (progressFrame) return;
  progressFrame = requestAnimationFrame(function () {
    progressFrame = 0;
    updateReadingProgress();
  });
}

window.addEventListener('scroll', scheduleReadingProgress, { passive: true });
window.addEventListener('resize', scheduleReadingProgress);
new ResizeObserver(scheduleReadingProgress).observe(document.body);
scheduleReadingProgress();

if (!motionPreference.matches && 'IntersectionObserver' in window) {
  const revealSelector = '.employee-card, .folder-card, .photo-card, .folder-toolbar, .folder-title, .story-feature, .admin-panel';
  const observed = new WeakSet();
  const revealObserver = new IntersectionObserver(function (entries) {
    let index = 0;
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      revealObserver.unobserve(entry.target);
      if (motionPreference.matches || entry.target.matches(':focus-within')) return;
      entry.target.style.setProperty('--reveal-delay', Math.min(index++, 5) * 55 + 'ms');
      entry.target.classList.add('motion-reveal');
    });
  }, { threshold: 0.08 });

  function observeReveals(container) {
    const elements = Array.from(container.querySelectorAll(revealSelector));
    if (container.matches(revealSelector)) elements.unshift(container);
    elements.forEach(function (element) {
      if (observed.has(element)) return;
      observed.add(element);
      revealObserver.observe(element);
    });
  }

  observeReveals(motionShell);
  new MutationObserver(function (records) {
    records.forEach(function (record) {
      record.removedNodes.forEach(function (node) {
        if (!(node instanceof Element) || motionShell.contains(node)) return;
        revealObserver.unobserve(node);
        observed.delete(node);
        node.querySelectorAll(revealSelector).forEach(function (element) {
          revealObserver.unobserve(element);
          observed.delete(element);
        });
      });
      record.addedNodes.forEach(function (node) {
        if (node instanceof Element) observeReveals(node);
      });
    });
  }).observe(motionShell, { childList: true, subtree: true });
}
