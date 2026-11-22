document.querySelectorAll('[data-story-open]').forEach(button => {
  const dialog = document.getElementById(button.dataset.storyOpen);
  const video = dialog.querySelector('video');
  button.addEventListener('click', () => {
    dialog.showModal();
    if (video) {
      video.loop = true;
      video.play().catch(() => {});
    }
  });
  dialog.addEventListener('close', () => {
    video?.pause();
    button.focus();
  });
  dialog.addEventListener('click', event => {
    if (event.target === dialog) {
      const rect = dialog.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right ||
          event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    }
  });
});

document.querySelectorAll('[data-story-qa]').forEach(group => {
  const questions = [...group.querySelectorAll('.story-question')];
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const animations = new WeakMap();
  const shown = { opacity: 1, transform: 'none' };
  const concealed = { opacity: 0, transform: 'translateY(9px) scale(0.988)' };
  let pinned = null;
  let visible = null;

  function show(question) {
    if (visible === question) return;
    const previous = visible;
    visible = question;
    [previous, question].filter(Boolean).forEach(button => {
      const opening = button === question;
      const answer = document.getElementById(button.getAttribute('aria-controls'));
      const current = animations.get(answer);
      const style = current && getComputedStyle(answer);
      const from = style ? { opacity: style.opacity, transform: style.transform } : (opening ? concealed : shown);
      current?.cancel();
      animations.delete(answer);
      answer.classList.toggle('is-leaving', !opening && !reducedMotion.matches);
      button.setAttribute('aria-expanded', String(opening));
      answer.setAttribute('aria-hidden', String(!opening));
      if (reducedMotion.matches) return;

      const animation = answer.animate([from, opening ? shown : concealed], {
        duration: opening ? 280 : 200,
        easing: 'cubic-bezier(0.2, 0.75, 0.25, 1)'
      });
      animations.set(answer, animation);
      animation.onfinish = () => {
        if (animations.get(answer) !== animation) return;
        animations.delete(answer);
        answer.classList.remove('is-leaving');
      };
    });
  }

  questions.forEach(button => {
    button.addEventListener('pointerenter', event => {
      if (event.pointerType === 'mouse') show(button);
    });
    button.addEventListener('click', () => {
      pinned = pinned === button ? null : button;
      show(pinned);
    });
  });
  group.addEventListener('pointerleave', event => {
    if (event.pointerType === 'mouse') show(pinned);
  });
  group.addEventListener('keydown', event => {
    if (event.key === 'Escape' && visible) {
      event.preventDefault();
      visible.focus();
      pinned = null;
      show(null);
    }
  });
  group.closest('dialog')?.addEventListener('close', () => {
    pinned = null;
    show(null);
  });
});
