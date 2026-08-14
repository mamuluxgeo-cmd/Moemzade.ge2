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

  const categoryTree = document.querySelector('[data-category-tree]');
  if (categoryTree) {
    const rootContainer = categoryTree.querySelector('[data-category-container][data-parent-id=""]');
    const rootDropzone = categoryTree.querySelector('[data-root-dropzone]');
    const orderForm = document.querySelector('[data-category-order-form]');
    const structureInput = orderForm && orderForm.querySelector('[data-category-structure]');
    const saveButton = document.querySelector('[data-category-save]');
    const orderStatus = document.querySelector('[data-category-order-status]');
    let draggedItem = null;
    let currentDrop = null;
    let dirty = false;

    const directItems = (container) => container
      ? Array.from(container.children).filter((child) => child.matches('[data-category-item]'))
      : [];
    const childContainer = (item) => item && item.querySelector(':scope > [data-category-children]');
    const hasChildren = (item) => directItems(childContainer(item)).length > 0;
    const clearDropState = () => {
      categoryTree.querySelectorAll('.drop-before, .drop-after, .drop-inside').forEach((item) => {
        item.classList.remove('drop-before', 'drop-after', 'drop-inside');
      });
      if (rootDropzone) rootDropzone.classList.remove('is-active');
      currentDrop = null;
    };

    const refreshItem = (item, parentId, sortOrder) => {
      const nested = parentId !== null;
      const children = directItems(childContainer(item));
      item.classList.toggle('is-child', nested);
      const collapse = item.querySelector(':scope > [data-category-row] [data-category-collapse]');
      if (collapse) {
        collapse.disabled = children.length === 0;
        if (children.length === 0) {
          item.classList.remove('is-collapsed');
          collapse.setAttribute('aria-expanded', 'true');
        }
      }
      const promote = item.querySelector(':scope > [data-category-row] [data-promote]');
      const demote = item.querySelector(':scope > [data-category-row] [data-demote]');
      if (promote) promote.hidden = !nested;
      if (demote) {
        demote.hidden = nested;
        demote.disabled = children.length > 0;
      }
      const parentSelect = item.querySelector(':scope > [data-category-editor] [data-parent-select]');
      if (parentSelect && Array.from(parentSelect.options).some((option) => option.value === String(parentId || ''))) {
        parentSelect.value = parentId === null ? '' : String(parentId);
      }
      const sortInput = item.querySelector(':scope > [data-category-editor] [data-sort-order]');
      if (sortInput) sortInput.value = String(sortOrder);
      children.forEach((child, index) => refreshItem(child, Number(item.dataset.categoryId), (index + 1) * 10));
    };

    const serialize = () => {
      const structure = [];
      directItems(rootContainer).forEach((root, rootIndex) => {
        const rootId = Number(root.dataset.categoryId);
        structure.push({ id: rootId, parent_id: null, sort_order: (rootIndex + 1) * 10 });
        directItems(childContainer(root)).forEach((child, childIndex) => {
          structure.push({
            id: Number(child.dataset.categoryId),
            parent_id: rootId,
            sort_order: (childIndex + 1) * 10,
          });
        });
        refreshItem(root, null, (rootIndex + 1) * 10);
      });
      if (structureInput) structureInput.value = JSON.stringify(structure);
      return structure;
    };

    const markDirty = () => {
      dirty = true;
      serialize();
      if (saveButton) saveButton.disabled = false;
      if (orderStatus) {
        orderStatus.textContent = 'შესანახი ცვლილებებია';
        orderStatus.classList.add('is-dirty');
      }
    };

    const insertBeforeDropzone = (item, reference = null) => {
      if (!rootContainer) return;
      rootContainer.insertBefore(item, reference || rootDropzone || null);
    };

    categoryTree.addEventListener('click', (event) => {
      const button = event.target.closest('button');
      const item = event.target.closest('[data-category-item]');
      if (!button || !item || !categoryTree.contains(item)) return;

      if (button.matches('[data-category-collapse]')) {
        const collapsed = item.classList.toggle('is-collapsed');
        button.setAttribute('aria-expanded', String(!collapsed));
        return;
      }
      if (button.matches('[data-category-edit]')) {
        const editor = item.querySelector(':scope > [data-category-editor]');
        if (!editor) return;
        const willOpen = editor.hidden;
        editor.hidden = !willOpen;
        button.setAttribute('aria-expanded', String(willOpen));
        return;
      }

      const container = item.parentElement;
      const siblings = directItems(container);
      const index = siblings.indexOf(item);
      if (button.matches('[data-move-up]') && index > 0) {
        container.insertBefore(item, siblings[index - 1]);
        markDirty();
      } else if (button.matches('[data-move-down]') && index >= 0 && index < siblings.length - 1) {
        container.insertBefore(siblings[index + 1], item);
        markDirty();
      } else if (button.matches('[data-promote]') && container !== rootContainer) {
        const parentItem = container.closest('[data-category-item]');
        insertBeforeDropzone(item, parentItem ? parentItem.nextElementSibling : null);
        markDirty();
      } else if (button.matches('[data-demote]') && container === rootContainer) {
        if (hasChildren(item)) {
          window.alert('ქვესფეროებიანი სფეროს სხვა სფეროში ჩაშლა შეუძლებელია.');
          return;
        }
        const previous = siblings[index - 1];
        if (!previous) {
          window.alert('ჩასაშლელად სფეროს წინ სხვა მთავარი სფერო უნდა იყოს.');
          return;
        }
        childContainer(previous).appendChild(item);
        previous.classList.remove('is-collapsed');
        markDirty();
      }
    });

    categoryTree.addEventListener('dragstart', (event) => {
      const handle = event.target.closest('[data-drag-handle]');
      if (!handle) return;
      draggedItem = handle.closest('[data-category-item]');
      if (!draggedItem) return;
      draggedItem.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', draggedItem.dataset.categoryId || '');
    });

    categoryTree.addEventListener('dragover', (event) => {
      if (!draggedItem) return;
      const row = event.target.closest('[data-category-row]');
      const target = row && row.closest('[data-category-item]');
      if (!target || target === draggedItem || draggedItem.contains(target)) return;

      const targetContainer = target.parentElement;
      const rect = row.getBoundingClientRect();
      const ratio = rect.height > 0 ? (event.clientY - rect.top) / rect.height : 0;
      let mode = ratio < 0.38 ? 'before' : 'after';
      if (targetContainer === rootContainer && ratio >= 0.38 && ratio <= 0.68) mode = 'inside';
      if ((mode === 'inside' || targetContainer !== rootContainer) && hasChildren(draggedItem)) return;

      event.preventDefault();
      event.dataTransfer.dropEffect = 'move';
      clearDropState();
      target.classList.add(`drop-${mode}`);
      currentDrop = { target, mode };
    });

    categoryTree.addEventListener('drop', (event) => {
      if (!draggedItem || !currentDrop) return;
      event.preventDefault();
      const { target, mode } = currentDrop;
      if (mode === 'inside') {
        childContainer(target).appendChild(draggedItem);
        target.classList.remove('is-collapsed');
      } else if (mode === 'before') {
        target.parentElement.insertBefore(draggedItem, target);
      } else {
        target.parentElement.insertBefore(draggedItem, target.nextSibling);
      }
      clearDropState();
      markDirty();
    });

    if (rootDropzone) {
      rootDropzone.addEventListener('dragover', (event) => {
        if (!draggedItem) return;
        event.preventDefault();
        clearDropState();
        rootDropzone.classList.add('is-active');
      });
      rootDropzone.addEventListener('drop', (event) => {
        if (!draggedItem) return;
        event.preventDefault();
        insertBeforeDropzone(draggedItem);
        clearDropState();
        markDirty();
      });
    }

    categoryTree.addEventListener('dragend', () => {
      if (draggedItem) draggedItem.classList.remove('is-dragging');
      draggedItem = null;
      clearDropState();
    });
    if (orderForm) orderForm.addEventListener('submit', () => {
      serialize();
      dirty = false;
    });
    window.addEventListener('beforeunload', (event) => {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });
    serialize();
  }

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
