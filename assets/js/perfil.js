document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('[data-profile-form]');
  const fileInput = document.querySelector('[data-profile-file]');
  const preview = document.querySelector('[data-profile-preview]');
  const removePhoto = document.querySelector('[data-profile-remove]');
  const uploadStatus = document.querySelector('[data-profile-upload-status]');

  if (!form || !fileInput || !preview) {
    return;
  }

  preview.dataset.currentImage = preview.src;
  let previewUrl = null;
  const restoreCurrentPreview = () => {
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
      previewUrl = null;
    }
    preview.src = preview.dataset.currentImage;
  };

  fileInput.addEventListener('change', () => {
    const file = fileInput.files?.[0];
    if (!file) {
      restoreCurrentPreview();
      return;
    }

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      fileInput.value = '';
      restoreCurrentPreview();
      if (uploadStatus) {
        uploadStatus.textContent = file.size > 5 * 1024 * 1024
          ? 'A imagem deve ter até 5 MB.'
          : 'Escolha uma imagem JPEG, PNG ou WebP.';
      }
      return;
    }

    if (uploadStatus) {
      uploadStatus.textContent = '';
    }
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
    }
    previewUrl = URL.createObjectURL(file);
    preview.src = previewUrl;
    if (removePhoto) {
      removePhoto.checked = false;
    }
  });

  removePhoto?.addEventListener('change', () => {
    if (removePhoto.checked) {
      fileInput.value = '';
      restoreCurrentPreview();
    }
  });
  window.addEventListener('beforeunload', () => {
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
    }
  });
});
