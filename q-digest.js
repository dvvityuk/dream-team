const digestHero = document.getElementById('digest-hero');
const digestContent = digestHero.querySelector('.digest-lightbox-content');
const digestImage = digestContent.querySelector('img');
const digestName = digestContent.querySelector('h2');
const digestText = digestContent.querySelector('p');

document.querySelectorAll('.digest-card-open').forEach(function (button) {
  button.addEventListener('click', function () {
    const card = button.closest('.digest-card');
    const avatar = card.querySelector('img');
    if (avatar) digestImage.src = avatar.src;
    else digestImage.removeAttribute('src');
    digestImage.alt = avatar?.alt ?? '';
    digestImage.hidden = !avatar;
    digestName.textContent = card.querySelector('h2').textContent;
    digestText.textContent = card.querySelector('p').textContent;
    digestHero.showModal();
    digestContent.scrollTop = 0;
  });
});

digestHero.addEventListener('click', function (event) {
  if (event.target === digestHero) digestHero.close();
});
