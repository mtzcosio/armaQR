/* =====================================================================
   Editor de documentos legales (Administración › Legales)
   Quill con títulos, listas, citas y enlaces; las {{variables}} se insertan
   donde estaba el cursor. El servidor limpia el HTML antes de guardarlo.
   ===================================================================== */
(function () {
  'use strict';

  const form = document.querySelector('form[data-editor-legal]');
  if (!form) return;
  const campo = document.getElementById('contenido_html');
  const estado = form.querySelector('[data-estado-cambios]');
  const contenedor = document.getElementById('editor-legal');

  if (typeof Quill === 'undefined') {
    // Sin el editor (sin conexión al CDN) se edita el HTML directamente.
    const area = document.createElement('textarea');
    area.className = 'entrada editor-html';
    area.value = campo.value;
    contenedor.replaceWith(area);
    form.addEventListener('submit', () => { campo.value = area.value; });
    return;
  }

  const quill = new Quill(contenedor, {
    theme: 'snow',
    placeholder: 'Escribe el documento…',
    modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'],
      [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link'], ['clean']] },
  });
  quill.clipboard.dangerouslyPasteHTML(campo.value || '', 'silent');

  // getSemanticHTML() de Quill 2 convierte los espacios en &nbsp;; se devuelven a espacios normales.
  const html = () => quill.getSemanticHTML().replace(/&nbsp;/g, ' ');

  let ultimoRango = null;
  quill.on('selection-change', r => { if (r) ultimoRango = r; });
  form.querySelectorAll('[data-insertar-variable]').forEach(b => b.addEventListener('click', () => {
    const token = '{{' + b.closest('[data-variable]').dataset.variable + '}}';
    const r = quill.getSelection(true) || ultimoRango || { index: quill.getLength(), length: 0 };
    quill.insertText(r.index, token, 'user');
    quill.setSelection(r.index + token.length, 0);
  }));

  // Cambios sin guardar
  let sucio = false;
  const marcar = () => {
    if (sucio) return;
    sucio = true;
    estado.textContent = 'Tienes cambios sin guardar';
    estado.classList.add('pendiente');
  };
  quill.on('text-change', (d, a, origen) => { if (origen === 'user') marcar(); });
  form.addEventListener('input', marcar);
  window.addEventListener('beforeunload', ev => { if (sucio) { ev.preventDefault(); ev.returnValue = ''; } });

  form.addEventListener('submit', () => {
    campo.value = html();
    sucio = false;
  });
})();
