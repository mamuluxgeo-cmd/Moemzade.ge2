(() => {
  const menuButton = document.querySelector('[data-menu-toggle]');
  const menu = document.querySelector('[data-menu]');
  if (menuButton && menu) {
    menuButton.addEventListener('click', () => {
      const open = menu.classList.toggle('open');
      menuButton.setAttribute('aria-expanded', String(open));
    });
  }

  document.querySelectorAll('.flash').forEach((element) => {
    window.setTimeout(() => element.classList.add('flash-hidden'), 6000);
  });

  document.querySelectorAll('[data-taxonomy-form]').forEach((form) => {
    const category = form.querySelector('[data-category-select]');
    const professionOptions = form.querySelector('[data-profession-options]');
    if (!category || !professionOptions) return;

    const choices = Array.from(professionOptions.querySelectorAll('option')).map((option) => ({
      value: option.value,
      category: option.dataset.category || '',
    }));
    const renderProfessionOptions = () => {
      const selectedCategory = category.value;
      const fragment = document.createDocumentFragment();
      choices
        .filter((choice) => !selectedCategory || !choice.category || choice.category === selectedCategory)
        .forEach((choice) => {
          const option = document.createElement('option');
          option.value = choice.value;
          option.dataset.category = choice.category;
          fragment.appendChild(option);
        });
      professionOptions.replaceChildren(fragment);
    };

    category.addEventListener('change', renderProfessionOptions);
    renderProfessionOptions();
  });

  document.querySelectorAll('[data-location-form]').forEach((form) => {
    const region = form.querySelector('[data-region-select]');
    const settlement = form.querySelector('[data-settlement-select]');
    if (!region || !settlement) return;

    const placeholder = settlement.querySelector('option:not([data-region])');
    const emptyLabel = placeholder ? placeholder.textContent : '—';
    const choices = Array.from(settlement.querySelectorAll('option[data-region]')).map((option) => ({
      value: option.value,
      label: option.textContent || option.value,
      region: option.dataset.region || '',
    }));

    const renderSettlements = () => {
      const selectedRegion = region.value;
      const previousValue = settlement.value;
      const fragment = document.createDocumentFragment();
      const emptyOption = document.createElement('option');
      emptyOption.value = '';
      emptyOption.textContent = emptyLabel;
      fragment.appendChild(emptyOption);

      choices
        .filter((choice) => selectedRegion && choice.region === selectedRegion)
        .forEach((choice) => {
          const option = document.createElement('option');
          option.value = choice.value;
          option.textContent = choice.label;
          option.dataset.region = choice.region;
          fragment.appendChild(option);
        });

      settlement.replaceChildren(fragment);
      settlement.disabled = !selectedRegion;
      if (selectedRegion && choices.some((choice) => choice.region === selectedRegion && choice.value === previousValue)) {
        settlement.value = previousValue;
      } else {
        settlement.value = '';
      }
    };

    region.addEventListener('change', renderSettlements);
    renderSettlements();
  });

  const registerForm = document.querySelector('[data-registration-form]');
  if (registerForm) {
    const panels = Array.from(registerForm.querySelectorAll('[data-step-panel]'));
    const dots = Array.from(document.querySelectorAll('[data-step-dot]'));
    const progress = registerForm.querySelector('[data-register-progress]');
    let currentStep = 1;

    const showStep = (number, shouldScroll = true) => {
      currentStep = Math.max(1, Math.min(3, Number(number) || 1));
      panels.forEach((panel) => {
        const active = Number(panel.dataset.stepPanel) === currentStep;
        panel.hidden = !active;
        panel.classList.toggle('active', active);
      });
      dots.forEach((dot) => {
        const value = Number(dot.dataset.stepDot);
        dot.classList.toggle('active', value === currentStep);
        dot.classList.toggle('done', value < currentStep);
      });
      if (progress) progress.style.width = `${currentStep * 33.333}%`;
      if (shouldScroll) registerForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const validatePanel = (panel) => {
      const controls = Array.from(panel.querySelectorAll('input, select, textarea'));
      const invalid = controls.find((control) => !control.checkValidity());
      const formats = panel.querySelectorAll('input[name="format_online"], input[name="format_in_person"]');
      if (formats.length && !Array.from(formats).some((input) => input.checked)) {
        formats[0].focus();
        return false;
      }
      if (invalid) {
        invalid.reportValidity();
        return false;
      }
      return true;
    };

    registerForm.querySelectorAll('[data-next-step]').forEach((button) => {
      button.addEventListener('click', () => {
        const panel = button.closest('[data-step-panel]');
        if (panel && validatePanel(panel)) showStep(button.dataset.nextStep);
      });
    });
    registerForm.querySelectorAll('[data-prev-step]').forEach((button) => {
      button.addEventListener('click', () => showStep(button.dataset.prevStep));
    });

    const unit = registerForm.querySelector('[data-price-unit]');
    const price = registerForm.querySelector('[data-price-input]');
    const syncPrice = () => {
      if (!unit || !price) return;
      const negotiable = unit.value === 'negotiable';
      price.disabled = negotiable;
      price.required = !negotiable;
      if (negotiable) price.value = '';
    };
    if (unit) unit.addEventListener('change', syncPrice);
    syncPrice();

    const photoInput = registerForm.querySelector('[data-photo-input]');
    const photoPreview = registerForm.querySelector('[data-photo-preview]');
    const photoPlaceholder = registerForm.querySelector('[data-photo-placeholder]');
    if (photoInput && photoPreview) {
      photoInput.addEventListener('change', () => {
        const file = photoInput.files && photoInput.files[0];
        if (!file) return;
        const objectUrl = URL.createObjectURL(file);
        photoPreview.src = objectUrl;
        photoPreview.hidden = false;
        if (photoPlaceholder) photoPlaceholder.hidden = true;
        photoPreview.onload = () => URL.revokeObjectURL(objectUrl);
      });
    }

    registerForm.addEventListener('submit', (event) => {
      for (const panel of panels) {
        if (!validatePanel(panel)) {
          event.preventDefault();
          showStep(panel.dataset.stepPanel);
          window.setTimeout(() => validatePanel(panel), 50);
          return;
        }
      }
      const submit = registerForm.querySelector('[data-register-submit]');
      if (submit) submit.disabled = true;
    });

    const firstError = registerForm.querySelector('.field-error');
    const errorPanel = firstError && firstError.closest('[data-step-panel]');
    showStep(errorPanel ? errorPanel.dataset.stepPanel : 1, false);
  }

  document.querySelectorAll('[data-copy-link]').forEach((button) => {
    button.addEventListener('click', async () => {
      const link = button.dataset.copyLink || window.location.href;
      try {
        await navigator.clipboard.writeText(link);
      } catch (_) {
        window.prompt('Copy link:', link);
      }
      const status = document.querySelector('[data-copy-status]');
      if (status) {
        status.classList.add('show');
        window.setTimeout(() => status.classList.remove('show'), 2200);
      }
    });
  });

  const photoModal = document.querySelector('[data-photo-modal]');
  const photoOpen = document.querySelector('[data-photo-open]');
  const photoClose = document.querySelector('[data-photo-close]');
  const closePhoto = () => {
    if (!photoModal) return;
    photoModal.hidden = true;
    document.body.classList.remove('modal-open');
    if (photoOpen) photoOpen.focus();
  };
  if (photoModal && photoOpen) {
    photoOpen.addEventListener('click', () => {
      photoModal.hidden = false;
      document.body.classList.add('modal-open');
      if (photoClose) photoClose.focus();
    });
    if (photoClose) photoClose.addEventListener('click', closePhoto);
    photoModal.addEventListener('click', (event) => {
      if (event.target === photoModal) closePhoto();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !photoModal.hidden) closePhoto();
    });
  }
})();
