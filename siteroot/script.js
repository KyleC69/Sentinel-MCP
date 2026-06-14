const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("visible");
        observer.unobserve(entry.target);
      }
    });
  },
  { threshold: 0.15 }
);

document.querySelectorAll(".reveal").forEach((el) => observer.observe(el));

const counters = document.querySelectorAll("[data-counter]");

const animateCounter = (el) => {
  const target = Number(el.dataset.counter || 0);
  const start = performance.now();
  const duration = 900;

  const tick = (time) => {
    const progress = Math.min((time - start) / duration, 1);
    const value = Math.floor(progress * target);
    el.textContent = String(value);
    if (progress < 1) {
      requestAnimationFrame(tick);
    }
  };

  requestAnimationFrame(tick);
};

const countObserver = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) {
        return;
      }
      animateCounter(entry.target);
      countObserver.unobserve(entry.target);
    });
  },
  { threshold: 0.5 }
);

counters.forEach((el) => countObserver.observe(el));
