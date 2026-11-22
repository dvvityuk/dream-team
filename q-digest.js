const digestPhoto = document.getElementById('digest-photo');
const digestImage = digestPhoto.querySelector('img');

document.querySelectorAll('.digest-photo-open').forEach(function (button) {
  button.addEventListener('click', function () {
    const avatar = button.querySelector('img');
    digestImage.src = avatar.src;
    digestImage.alt = avatar.alt;
    digestPhoto.showModal();
  });
});

digestPhoto.addEventListener('click', function (event) {
  if (event.target === digestPhoto) digestPhoto.close();
});
