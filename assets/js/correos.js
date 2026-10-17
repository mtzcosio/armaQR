/* =====================================================================
   Módulo de plantillas de correo (Administración › Correos)
   - Listado: diálogo de "Enviar prueba".
   - Variables: diálogo para crear y editar.
   - Editor: Quill o HTML, variables, vista previa y prueba de lo que está en pantalla.
   ===================================================================== */
(function () {
  'use strict';

  const $ = (sel, raiz = document) => raiz.querySelector(sel);
  const $$ = (sel, raiz = document) => Array.from(raiz.querySelectorAll(sel));
  const PATRON = /\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g;

  /* ---------- Listado: enviar prueba ---------- */
  const dialogoPrueba = $('#dialogo-prueba');
  $$('[data-abrir-prueba]').forEach(b => b.addEventListener('click', () => {
    $('[data-prueba-id]', dialogoPrueba).value = b.dataset.abrirPrueba;
    $('[data-prueba-nombre]', dialogoPrueba).textContent = '«' + b.dataset.nombre + '»';
    dialogoPrueba.showModal();
  }));

  /* ---------- Variables: crear / editar ---------- */
  const dialogoVariable = $('#dialogo-variable');
  if (dialogoVariable) {
    const campo = nombre => $('[name="' + nombre + '"]', dialogoVariable);
    const avisoRenombrar = $('[data-aviso-renombrar]', dialogoVariable);
    const abrir = datos => {
      $('[data-variable-titulo]', dialogoVariable).textContent = datos.id ? 'Editar variable' : 'Nueva variable';
      $('[data-variable-id]', dialogoVariable).value = datos.id || '';
      ['nombre', 'categoria', 'descripcion', 'valor_ejemplo', 'valor'].forEach(c => { campo(c).value = datos[c] || ''; });
      avisoRenombrar.hidden = true;
      campo('nombre').oninput = () => {
        // Renombrar rompe las plantillas que ya la usan: se avisa antes de guardar.
        const cambia = datos.id && datos.usos && campo('nombre').value !== datos.nombre;
        avisoRenombrar.hidden = !cambia;
        avisoRenombrar.textContent = cambia ? 'La usan ' + datos.usos + ' plantilla(s): tendrás que cambiar {{' + datos.nombre + '}} en ellas.' : '';
      };
      dialogoVariable.showModal();
      campo('nombre').focus();
    };
    $$('[data-nueva-variable]').forEach(b => b.addEventListener('click', () => abrir({})));
    $$('[data-editar-variable]').forEach(b => b.addEventListener('click', () => abrir(JSON.parse(b.dataset.editarVariable))));
  }

  /* ---------- Editor ---------- */
  const form = $('form[data-editor-correo]');
  if (!form || !window.CORREO) return;

  const C = window.CORREO;
  const campoHtml = $('#contenido_html');
  const textarea = $('#editor-html');
  const contenedorVisual = $('#editor-visual');
  const asunto = $('#asunto');
  const preheader = $('#preheader');
  const codigo = $('#codigo');
  const nombre = $('#nombre');
  const botonModo = $('[data-cambiar-modo]');
  const notaModo = $('[data-nota-modo]');

  // HTML que el editor visual no puede representar sin perder información.
  const esComplejo = html => /<(table|style|body|html|td|center)\b/i.test(html) || /\sstyle=/i.test(html);

  /* --- Quill --- */
  let quill = null;
  if (typeof Quill !== 'undefined') {
    // Alineación como estilo en línea (las clases de Quill no funcionan en los correos).
    Quill.register(Quill.import('attributors/style/align'), true);
    // Separador <hr>: Quill no lo trae.
    const BlockEmbed = Quill.import('blots/block/embed');
    class Separador extends BlockEmbed {
      static create() {
        const nodo = super.create();
        nodo.setAttribute('style', 'border:none;border-top:1px solid #DDE1EA;margin:20px 0;');
        return nodo;
      }
    }
    Separador.blotName = 'separador';
    Separador.tagName = 'hr';
    Quill.register(Separador);

    quill = new Quill(contenedorVisual, {
      theme: 'snow',
      placeholder: 'Escribe el contenido del correo…',
      modules: {
        toolbar: {
          container: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], [{ align: [] }],
                      [{ list: 'ordered' }, { list: 'bullet' }], ['link', 'image'], ['clean']],
          handlers: {
            image() {
              const direccion = prompt('Dirección completa de la imagen (https://…)');
              if (!direccion) return;
              if (!/^https?:\/\//i.test(direccion)) { alert('Usa una dirección completa que empiece con https://'); return; }
              const rango = cursorQuill();
              quill.insertEmbed(rango.index, 'image', direccion, 'user');
            },
          },
        },
      },
    });
  }

  let modo = 'visual';
  let rangoQuill = null;
  // getSemanticHTML() de Quill 2 convierte los espacios en &nbsp;; en un correo eso impide el salto de línea.
  const htmlDeQuill = () => quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
  const htmlActual = () => (modo === 'html' || !quill) ? textarea.value : htmlDeQuill();
  const cursorQuill = () => quill.getSelection(true) || rangoQuill || { index: quill.getLength(), length: 0 };

  function ponerModo(nuevo, html) {
    modo = quill ? nuevo : 'html';
    if (modo === 'html') {
      textarea.value = html;
    } else {
      quill.setContents([], 'silent');
      quill.clipboard.dangerouslyPasteHTML(html, 'silent');
    }
    textarea.hidden = modo !== 'html';
    if (quill) $('.ql-toolbar', form).hidden = contenedorVisual.hidden = modo === 'html';
    botonModo.textContent = modo === 'html' ? 'Editor visual' : 'HTML';
    botonModo.setAttribute('aria-pressed', String(modo === 'html'));
    botonModo.hidden = !quill;
    notaModo.textContent = !quill
      ? 'El editor visual no cargó; puedes editar el HTML directamente.'
      : (modo === 'html' ? 'Modo HTML: úsalo para correos con tablas y estilos en línea.' : 'Editor visual: ideal para correos sencillos. Para diseños con tablas usa HTML.');
  }

  // Los correos con tablas o estilos se abren en HTML para no perder su diseño.
  ponerModo(esComplejo(campoHtml.value) ? 'html' : 'visual', campoHtml.value);

  botonModo.addEventListener('click', () => {
    const html = htmlActual();
    if (modo === 'html' && esComplejo(html)
        && !confirm('El editor visual puede quitar tablas y estilos de este HTML. ¿Cambiar de todos modos?')) return;
    ponerModo(modo === 'html' ? 'visual' : 'html', html);
    revisar();
  });

  /* --- Insertar donde estaba el cursor --- */
  let destino = null; // asunto | preheader | textarea | quill
  $$('[data-destino-variable]', form).forEach(el => el.addEventListener('focus', () => { destino = el; }));
  if (quill) {
    quill.on('selection-change', rango => { if (rango) { rangoQuill = rango; destino = 'quill'; } });
  }

  function insertarEnCampo(el, texto) {
    const inicio = el.selectionStart ?? el.value.length;
    const fin = el.selectionEnd ?? el.value.length;
    el.value = el.value.slice(0, inicio) + texto + el.value.slice(fin);
    el.focus();
    el.selectionStart = el.selectionEnd = inicio + texto.length;
    el.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function insertar(texto, html) {
    if (destino && destino !== 'quill' && !destino.hidden) {
      insertarEnCampo(destino, destino === textarea && html ? html : texto);
    } else if (modo === 'visual' && quill) {
      const rango = cursorQuill();
      if (html) quill.clipboard.dangerouslyPasteHTML(rango.index, html, 'user');
      else quill.insertText(rango.index, texto, 'user');
      quill.setSelection(rango.index + (html ? 1 : texto.length), 0);
    } else {
      insertarEnCampo(textarea, html || texto);
    }
  }

  $$('[data-insertar-variable]').forEach(b => b.addEventListener('click', () => {
    insertar('{{' + b.closest('[data-variable]').dataset.variable + '}}');
  }));
  $$('[data-copiar-variable]').forEach(b => b.addEventListener('click', async () => {
    const token = '{{' + b.closest('[data-variable]').dataset.variable + '}}';
    try { await navigator.clipboard.writeText(token); window.notificar && window.notificar('Copiaste ' + token); }
    catch (e) { window.notificar && window.notificar('No se pudo copiar; selecciónalo manualmente'); }
  }));

  const htmlBoton = '<p style="text-align:center;margin:24px 0;"><a href="{{url_portal}}" style="display:inline-block;padding:13px 26px;'
    + 'background:{{color_acento}};color:#FFFFFF;text-decoration:none;font-weight:bold;border-radius:8px;">Texto del botón</a></p>';
  $('[data-insertar-boton]').addEventListener('click', () => {
    if (modo === 'visual' && quill) {
      // En el editor visual el botón queda como enlace; su estilo se conserva al editarlo en HTML.
      const rango = cursorQuill();
      quill.insertText(rango.index, 'Texto del botón', { link: '{{url_portal}}', bold: true }, 'user');
    } else {
      destino = textarea;
      insertarEnCampo(textarea, htmlBoton);
    }
  });
  $('[data-insertar-separador]').addEventListener('click', () => {
    if (modo === 'visual' && quill) {
      const rango = cursorQuill();
      quill.insertEmbed(rango.index, 'separador', true, 'user');
      quill.setSelection(rango.index + 1, 0);
    } else {
      destino = textarea;
      insertarEnCampo(textarea, '<hr style="border:none;border-top:1px solid #DDE1EA;margin:20px 0;">');
    }
  });

  /* --- Buscador de variables --- */
  const buscador = $('[data-buscar-variable]');
  buscador.addEventListener('input', () => {
    const q = buscador.value.trim().toLowerCase();
    let visibles = 0;
    $$('.variables-grupo').forEach(grupo => {
      let enGrupo = 0;
      $$('[data-variable]', grupo).forEach(li => {
        const ok = !q || li.dataset.textoBusqueda.includes(q);
        li.hidden = !ok;
        if (ok) enGrupo++;
      });
      grupo.hidden = enGrupo === 0;
      if (q && enGrupo) grupo.open = true;
      visibles += enGrupo;
    });
    $('[data-sin-variables]').hidden = visibles > 0;
  });

  /* --- Validación en vivo: variables desconocidas o que el código no llena --- */
  const avisoDesconocidas = $('[data-variables-desconocidas]');
  const avisoSinDato = $('[data-variables-sin-dato]');
  const usadas = texto => [...String(texto).matchAll(PATRON)].map(m => m[1].toLowerCase()).filter((v, i, a) => a.indexOf(v) === i);
  const lista = nombres => nombres.map(n => '{{' + n + '}}').join(', ');

  function revisar() {
    const todas = usadas(asunto.value + ' ' + preheader.value + ' ' + htmlActual());
    const desconocidas = todas.filter(v => !C.variables.includes(v));
    avisoDesconocidas.hidden = !desconocidas.length;
    $('span', avisoDesconocidas).textContent = desconocidas.length
      ? 'No existen en el catálogo y no te dejará guardar: ' + lista(desconocidas) + '.' : '';

    // En un correo del sistema, una variable tiene dato si la llena su código, si es del portal
    // (se llenan en todos los envíos) o si es personalizada con valor real.
    const delCodigo = C.sistema[codigo.value.trim().toUpperCase()];
    const conDato = v => delCodigo.includes(v) || C.siempre.includes(v);
    const sinDato = delCodigo ? todas.filter(v => C.variables.includes(v) && !conDato(v)) : [];
    avisoSinDato.hidden = !sinDato.length;
    $('span', avisoSinDato).textContent = sinDato.length
      ? 'Este correo del sistema no llena ' + lista(sinDato) + ': en el envío real saldrán vacías.' : '';
  }

  /* --- Cambios sin guardar --- */
  const estado = $('[data-estado-cambios]');
  let sucio = false;
  const marcar = () => {
    if (!sucio) { sucio = true; estado.textContent = 'Tienes cambios sin guardar'; estado.classList.add('pendiente'); }
    revisar();
  };
  form.addEventListener('input', marcar);
  form.addEventListener('change', marcar);
  if (quill) quill.on('text-change', (delta, anterior, origen) => { if (origen === 'user') marcar(); });
  window.addEventListener('beforeunload', ev => { if (sucio) { ev.preventDefault(); ev.returnValue = ''; } });

  // Si el código está vacío, se sugiere a partir del nombre.
  codigo.placeholder = 'Se genera del nombre';
  nombre.addEventListener('input', () => {
    if (codigo.value.trim()) return;
    const sugerido = nombre.value.normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    codigo.placeholder = sugerido || 'Se genera del nombre';
  });
  codigo.addEventListener('input', () => { codigo.value = codigo.value.toUpperCase().replace(/[^A-Z0-9_]/g, '_'); });

  /* --- Vista previa --- */
  const textoPlano = html => { const t = document.createElement('textarea'); t.innerHTML = html; return t.value; };
  const render = texto => String(texto || '').replace(PATRON, (m, n) => Object.prototype.hasOwnProperty.call(C.ejemplo, n.toLowerCase()) ? C.ejemplo[n.toLowerCase()] : m);
  function pintarPrevia() {
    const rn = $('#remitente_nombre').value.trim(), rc = $('#remitente_correo').value.trim();
    $('[data-previa-de]').textContent = rn || rc ? (rn || C.remitente.split(' <')[0]) + ' <' + (rc || C.remitente.split(' <')[1]?.replace('>', '')) + '>' : C.remitente;
    $('[data-previa-asunto]').textContent = textoPlano(render(asunto.value)) || '(sin asunto)';
    $('[data-previa-preheader]').textContent = textoPlano(render(preheader.value)) || '(sin preheader)';
    $('[data-previa-marco]').srcdoc = render(htmlActual()) || '<p style="font-family:Arial;color:#8A92A6;padding:20px">Sin contenido todavía.</p>';
  }
  $$('[data-vista]').forEach(tab => tab.addEventListener('click', () => {
    const vista = tab.dataset.vista;
    $$('[data-vista]').forEach(t => t.setAttribute('aria-selected', String(t === tab)));
    $$('[data-panel-vista]').forEach(p => { p.hidden = p.dataset.panelVista !== vista; });
    $('[data-solo-editar]').hidden = vista !== 'editar';
    if (vista === 'previa') pintarPrevia();
  }));

  /* --- Guardar y enviar prueba --- */
  const accion = $('[data-accion]');
  form.addEventListener('submit', () => {
    campoHtml.value = htmlActual();
    sucio = false;
  });
  $$('[data-guardar]').forEach(b => b.addEventListener('click', () => { accion.value = 'guardar'; }));

  const dialogoPruebaEditor = $('#dialogo-prueba-editor');
  const correoPrueba = $('#correo_prueba');
  $('[data-abrir-prueba-editor]').addEventListener('click', () => { dialogoPruebaEditor.showModal(); correoPrueba.focus(); });
  $('[data-enviar-prueba]').addEventListener('click', () => {
    if (!correoPrueba.value.trim() || !correoPrueba.checkValidity()) {
      correoPrueba.setCustomValidity('Escribe un correo válido.');
      correoPrueba.reportValidity();
      correoPrueba.setCustomValidity('');
      return;
    }
    accion.value = 'probar';
    dialogoPruebaEditor.close();
    form.requestSubmit();
  });

  revisar();
})();
