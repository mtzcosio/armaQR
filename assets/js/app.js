/* =====================================================================
   Comportamiento de las pantallas (sin dependencias propias)
   Usa qrcodejs y Chart.js desde CDN cuando la página los carga.
   ===================================================================== */
(function () {
  'use strict';

  const $  = (sel, raiz = document) => raiz.querySelector(sel);
  const $$ = (sel, raiz = document) => Array.from(raiz.querySelectorAll(sel));

  /* ---------- Notificación breve ---------- */
  let temporizador;
  function notificar(texto) {
    let n = $('.notificacion');
    if (!n) {
      n = document.createElement('div');
      n.className = 'notificacion';
      n.setAttribute('role', 'status');
      document.body.appendChild(n);
    }
    n.textContent = texto;
    n.classList.add('visible');
    clearTimeout(temporizador);
    temporizador = setTimeout(() => n.classList.remove('visible'), 2600);
  }
  window.notificar = notificar;

  /* ---------- Menú lateral en móvil ---------- */
  const panel = $('.panel');
  $$('[data-abrir-lateral]').forEach(b => b.addEventListener('click', () => panel.classList.add('lateral-abierto')));
  $$('[data-cerrar-lateral]').forEach(b => b.addEventListener('click', () => panel.classList.remove('lateral-abierto')));

  /* ---------- Tema claro / oscuro ---------- */
  // El botón y el tema los controla el script de <head> (includes/tema.php), para que
  // funcionen aunque este archivo tarde en cargar. Aquí solo se escucha "tema-cambiado".
  const raiz = document.documentElement;

  /* ---------- Pestañas de configuración administrativa ---------- */
  // La sección activa se guarda en la URL (#correo) y en el campo "seccion" para volver a ella al guardar.
  const seccionActiva = $('[data-seccion-activa]');
  function abrirSeccion(nombre) {
    const tab = $$('[data-tab]').find(t => t.dataset.tab === nombre);
    if (!tab) return false;
    $$('[data-tab]').forEach(x => {
      const activo = x === tab;
      x.classList.toggle('activo', activo);
      x.setAttribute('aria-selected', String(activo));
      x.tabIndex = activo ? 0 : -1;
    });
    $$('[data-tab-panel]').forEach(panel => { panel.hidden = panel.dataset.tabPanel !== nombre; });
    if (seccionActiva) seccionActiva.value = nombre;
    // En celular el menú se desplaza en horizontal: deja visible la sección activa.
    const menu = tab.parentElement;
    if (menu.scrollWidth > menu.clientWidth) {
      menu.scrollLeft = tab.offsetLeft - (menu.clientWidth - tab.offsetWidth) / 2;
    }
    return true;
  }
  $$('[data-tab]').forEach(tab => {
    tab.addEventListener('click', () => {
      abrirSeccion(tab.dataset.tab);
      history.replaceState(null, '', '#' + tab.dataset.tab);
    });
    tab.addEventListener('keydown', evento => {
      const adelante = ['ArrowRight', 'ArrowDown'].includes(evento.key);
      const atras = ['ArrowLeft', 'ArrowUp'].includes(evento.key);
      if (!adelante && !atras) return;
      evento.preventDefault();
      const tabs = $$('[data-tab]');
      const indice = tabs.indexOf(tab);
      const siguiente = adelante ? (indice + 1) % tabs.length : (indice - 1 + tabs.length) % tabs.length;
      tabs[siguiente].focus();
      tabs[siguiente].click();
    });
  });
  if ($('[data-tab]')) {
    abrirSeccion(location.hash.slice(1).replace(/^panel-/, '')) || abrirSeccion($('[data-tab]').dataset.tab);
  }

  /* ---------- Formulario de configuración ---------- */
  const ajustes = $('form[data-ajustes]');
  if (ajustes) {
    // Si un campo obligatorio está en una sección oculta, se abre esa sección para mostrar el error.
    ajustes.addEventListener('invalid', evento => {
      const panel = evento.target.closest('[data-tab-panel]');
      if (panel && panel.hidden) abrirSeccion(panel.dataset.tabPanel);
    }, true);

    // Aviso de cambios sin guardar.
    const estado = $('[data-estado-cambios]', ajustes);
    const marcarPendiente = () => {
      estado.textContent = 'Tienes cambios sin guardar';
      estado.classList.add('pendiente');
    };
    ajustes.addEventListener('input', marcarPendiente);
    ajustes.addEventListener('change', marcarPendiente);

    // Puerto y cifrado SMTP que no combinan (465 va con SSL; 587 con TLS).
    const puerto = $('#smtp_port', ajustes);
    const cifrado = $('#smtp_encryption', ajustes);
    const avisoSmtp = $('[data-aviso-smtp]', ajustes);
    const revisarSmtp = () => {
      const p = parseInt(puerto.value, 10);
      let texto = '';
      if (p === 465 && cifrado.value !== 'ssl') texto = 'El puerto 465 normalmente usa SSL. Con ' + cifrado.value.toUpperCase() + ' el envío puede fallar.';
      if (p === 587 && cifrado.value === 'ssl') texto = 'El puerto 587 normalmente usa TLS. Con SSL el envío puede fallar.';
      avisoSmtp.hidden = texto === '';
      $('span', avisoSmtp).textContent = texto;
    };
    if (puerto && cifrado && avisoSmtp) {
      puerto.addEventListener('input', revisarSmtp);
      cifrado.addEventListener('change', revisarSmtp);
      revisarSmtp();
    }
  }

  /* ---------- Cabecera pública con borde al hacer scroll ---------- */
  const cabecera = $('.cabecera');
  if (cabecera) {
    const revisar = () => cabecera.classList.toggle('con-borde', window.scrollY > 8);
    window.addEventListener('scroll', revisar, { passive: true });
    revisar();
  }

  /* ---------- Generación de QR ---------- */
  // Usa qrcode-generator (cdnjs). El texto se codifica en UTF-8 para que acentos, ñ y símbolos
  // se lean bien: la librería anterior (qrcodejs) fallaba con ellos o agregaba un carácter invisible.
  const MARGEN_QR = 4; // zona silenciosa en módulos (la exige el estándar para que se lea bien)

  /** Matriz del QR, o null si no cargó la librería. Lanza error si el contenido no cabe. */
  function matrizQR(texto, nivelAlto) {
    if (typeof qrcode === 'undefined') return null;
    qrcode.stringToBytes = s => Array.from(new TextEncoder().encode(s));
    const qr = qrcode(0, nivelAlto ? 'H' : 'M');
    qr.addData(texto || ' ', 'Byte');
    qr.make();
    return qr;
  }

  /** Lado y escala enteros para que cada módulo mida lo mismo (bordes nítidos). */
  function medidasQR(qr, px) {
    const total = qr.getModuleCount() + MARGEN_QR * 2;
    const escala = Math.max(1, Math.floor(px / total));
    return { total, escala, lado: escala * total };
  }

  /** Dibuja el QR en un canvas: colores, logo opcional al centro y marca de agua opcional abajo. */
  function dibujarQR(canvas, qr, { px, color = '#172036', fondo = '#ffffff', logo = null, marca = '' }) {
    const { escala, lado } = medidasQR(qr, px);
    const banda = marca ? Math.round(lado * 0.09) : 0;
    canvas.width = lado;
    canvas.height = lado + banda;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = fondo;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = color;
    const n = qr.getModuleCount();
    for (let f = 0; f < n; f++) {
      for (let c = 0; c < n; c++) {
        if (qr.isDark(f, c)) ctx.fillRect((c + MARGEN_QR) * escala, (f + MARGEN_QR) * escala, escala, escala);
      }
    }
    if (logo) {
      const caja = Math.round(lado * 0.22);
      const borde = Math.round(caja * 0.12);
      const x = Math.round((lado - caja) / 2);
      ctx.fillStyle = '#ffffff';
      ctx.beginPath();
      if (ctx.roundRect) ctx.roundRect(x - borde, x - borde, caja + borde * 2, caja + borde * 2, borde);
      else ctx.rect(x - borde, x - borde, caja + borde * 2, caja + borde * 2);
      ctx.fill();
      const prop = Math.min(caja / logo.naturalWidth, caja / logo.naturalHeight);
      const w = logo.naturalWidth * prop, h = logo.naturalHeight * prop;
      ctx.drawImage(logo, x + (caja - w) / 2, x + (caja - h) / 2, w, h);
    }
    if (marca) {
      ctx.globalAlpha = 0.7;
      ctx.fillStyle = color;
      ctx.font = Math.round(banda * 0.42) + 'px Arial, sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(marca, lado / 2, lado + banda / 2 - escala);
      ctx.globalAlpha = 1;
    }
  }

  /** Pinta un QR dentro de un elemento (miniaturas y vistas previas). Devuelve la matriz o null. */
  function pintarQR(el, texto, opciones = {}) {
    let qr;
    try {
      qr = matrizQR(texto, opciones.nivelAlto);
    } catch (e) {
      el.innerHTML = '<span class="texto-gris texto-chico">El contenido es demasiado largo para un QR. Acórtalo.</span>';
      return null;
    }
    if (!qr) {
      el.innerHTML = '<span class="texto-gris texto-chico">Vista previa no disponible sin conexión</span>';
      return null;
    }
    let canvas = el.querySelector('canvas');
    if (!canvas) {
      el.innerHTML = '';
      canvas = document.createElement('canvas');
      el.appendChild(canvas);
    }
    const tam = opciones.tam || parseInt(el.dataset.qrTam || '220', 10);
    dibujarQR(canvas, qr, { px: tam * 2, color: opciones.color || el.dataset.qrColor, fondo: opciones.fondo, logo: opciones.logo });
    return qr;
  }

  // QR estáticos en listas (miniaturas)
  $$('[data-qr]').forEach(el => pintarQR(el, el.dataset.qr, { tam: parseInt(el.dataset.qrTam || '52', 10) }));

  /* --- Contenido según el tipo --- */
  // vCard 3.0: escapa \ , ; y saltos de línea en los valores.
  const valorVcard = s => String(s || '').replace(/([\\,;])/g, '\\$1').replace(/\r?\n/g, '\\n');

  function construirContenido(tipo, v) {
    switch (tipo) {
      case 'url': {
        let u = (v.url || '').trim();
        if (u && !/^[a-z][a-z0-9+.-]*:/i.test(u)) u = 'https://' + u;
        return u;
      }
      case 'whatsapp': {
        const tel = (v.telefono || '').replace(/\D/g, '');
        const num = tel.length === 10 ? '52' + tel : tel;
        return 'https://wa.me/' + num + (v.mensaje ? '?text=' + encodeURIComponent(v.mensaje) : '');
      }
      case 'wifi': {
        const esc = s => (s || '').replace(/([\\;,:"])/g, '\\$1');
        const seg = v.seguridad || 'WPA';
        return 'WIFI:T:' + seg + ';S:' + esc(v.red) + ';' + (seg === 'nopass' ? '' : 'P:' + esc(v.clave) + ';') + ';';
      }
      case 'vcard':
        // N es obligatorio en vCard 3.0 (sin él, iPhone no ofrece guardar el contacto).
        return ['BEGIN:VCARD', 'VERSION:3.0', 'N:;' + valorVcard(v.nombre) + ';;;', 'FN:' + valorVcard(v.nombre),
          v.empresa ? 'ORG:' + valorVcard(v.empresa) : '', v.telefono ? 'TEL:' + valorVcard(v.telefono) : '',
          v.email ? 'EMAIL:' + valorVcard(v.email) : '', 'END:VCARD'].filter(Boolean).join('\n');
      case 'email':
        return 'mailto:' + (v.email || '') + (v.asunto ? '?subject=' + encodeURIComponent(v.asunto) : '');
      default:
        return v.texto || '';
    }
  }

  /* --- Contraste: un QR claro sobre fondo oscuro o con poco contraste no lo leen muchos celulares --- */
  function luminancia(hex) {
    const c = (hex || '#000000').replace('#', '').match(/../g).map(x => parseInt(x, 16) / 255)
      .map(v => v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }
  function problemaContraste(color, fondo) {
    const lc = luminancia(color), lf = luminancia(fondo);
    if (lc > lf) return 'El código es más claro que el fondo: muchos celulares no leen QR invertidos.';
    if ((lf + 0.05) / (lc + 0.05) < 4) return 'Hay poco contraste entre el código y el fondo: puede costar leerlo.';
    return '';
  }

  /* --- Descargas --- */
  function descargarArchivo(blob, nombre) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = nombre;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
  }
  const nombreArchivo = base => (base || 'codigo-qr').normalize('NFD').replace(/[̀-ͯ]/g, '')
    .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'codigo-qr';
  const escaparXml = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' }[c]));

  /** SVG vectorial (para imprenta): un solo path con los módulos. */
  function svgQR(qr, { color, fondo, logoUrl, marca }) {
    const n = qr.getModuleCount(), total = n + MARGEN_QR * 2, banda = marca ? +(total * 0.09).toFixed(2) : 0;
    let d = '';
    for (let f = 0; f < n; f++) for (let c = 0; c < n; c++) if (qr.isDark(f, c)) d += 'M' + (c + MARGEN_QR) + ',' + (f + MARGEN_QR) + 'h1v1h-1z';
    let svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + total + ' ' + (total + banda) + '" width="' + total * 10 + '" height="' + Math.round((total + banda) * 10) + '" shape-rendering="crispEdges">'
      + '<rect width="100%" height="100%" fill="' + fondo + '"/><path d="' + d + '" fill="' + color + '"/>';
    if (logoUrl) {
      const caja = total * 0.22, borde = caja * 0.12, x = (total - caja) / 2;
      svg += '<rect x="' + (x - borde) + '" y="' + (x - borde) + '" width="' + (caja + borde * 2) + '" height="' + (caja + borde * 2) + '" rx="' + borde + '" fill="#ffffff"/>'
        + '<image href="' + escaparXml(logoUrl) + '" x="' + x + '" y="' + x + '" width="' + caja + '" height="' + caja + '" preserveAspectRatio="xMidYMid meet"/>';
    }
    if (marca) {
      svg += '<text x="' + total / 2 + '" y="' + (total + banda / 2 - 0.5) + '" font-family="Arial, sans-serif" font-size="' + (banda * 0.42).toFixed(2)
        + '" text-anchor="middle" dominant-baseline="middle" fill="' + color + '" fill-opacity="0.7">' + escaparXml(marca) + '</text>';
    }
    return svg + '</svg>';
  }

  /** PDF de una página carta con el QR centrado (imagen JPEG; sin librerías). */
  function pdfDeCanvas(canvas) {
    const jpg = atob(canvas.toDataURL('image/jpeg', 0.95).split(',')[1]);
    const ancho = 360, alto = +(ancho * canvas.height / canvas.width).toFixed(2);
    const x = (612 - ancho) / 2, y = +((792 - alto) / 2).toFixed(2);
    const dibujo = 'q ' + ancho + ' 0 0 ' + alto + ' ' + x + ' ' + y + ' cm /Im0 Do Q';
    const objetos = [
      '<< /Type /Catalog /Pages 2 0 R >>',
      '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
      '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>',
      '<< /Type /XObject /Subtype /Image /Width ' + canvas.width + ' /Height ' + canvas.height
        + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + jpg.length + ' >>\nstream\n' + jpg + '\nendstream',
      '<< /Length ' + dibujo.length + ' >>\nstream\n' + dibujo + '\nendstream',
    ];
    let pdf = '%PDF-1.4\n%âãÏÓ\n';
    const posiciones = objetos.map((obj, i) => { const pos = pdf.length; pdf += (i + 1) + ' 0 obj\n' + obj + '\nendobj\n'; return pos; });
    const xref = pdf.length;
    pdf += 'xref\n0 ' + (objetos.length + 1) + '\n0000000000 65535 f \n'
      + posiciones.map(p => String(p).padStart(10, '0') + ' 00000 n \n').join('')
      + 'trailer\n<< /Size ' + (objetos.length + 1) + ' /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF';
    return new Blob([Uint8Array.from(pdf, ch => ch.charCodeAt(0))], { type: 'application/pdf' });
  }

  function debounce(fn, ms) {
    let t;
    return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
  }

  /* --- Generadores en vivo (página pública y "Crear QR") --- */
  $$('[data-generador]').forEach(form => {
    const vista = document.getElementById(form.dataset.generador) || document;
    const destino = $('[data-qr-vivo]', vista);
    const salida = $('[data-contenido-salida]', vista);
    const avisoContraste = $('[data-aviso-contraste]', form);
    const estado = { texto: '', color: '#172036', fondo: '#ffffff', logo: null, logoUrl: '' };
    let tipo = (form.querySelector('[name="tipo_contenido"]:checked') || {}).value || form.dataset.tipoInicial || 'url';

    const esDinamico = () => (form.querySelector('[name="tipo"]:checked') || {}).value === 'dinamico';
    // Un dinámico nuevo todavía no tiene liga corta: su QR no llevaría a ningún lado.
    const dinamicoSinCodigo = () => esDinamico() && !form.dataset.codigo;

    function leer() {
      const v = {};
      $$('[data-campo]', form).forEach(c => {
        const panelCampo = c.closest('[data-panel-tipo]');
        if (!panelCampo || panelCampo.dataset.panelTipo === tipo) v[c.dataset.campo] = c.value;
      });
      return v;
    }

    function actualizar(animar = true) {
      let texto = construirContenido(tipo, leer());
      if (esDinamico()) {
        texto = 'https://' + (form.dataset.dominio || location.host) + '/r/' + (form.dataset.codigo || 'NUEVO');
      }
      estado.texto = texto || 'https://' + (form.dataset.dominio || location.host);
      estado.color = (form.querySelector('[data-color-qr]') || {}).value || '#172036';
      estado.fondo = (form.querySelector('[data-fondo-qr]') || {}).value || '#ffffff';
      pintarQR(destino, estado.texto, { color: estado.color, fondo: estado.fondo, logo: estado.logo, nivelAlto: !!estado.logo });
      if (animar) {
        destino.classList.remove('latido');
        void destino.offsetWidth;
        destino.classList.add('latido');
      }
      if (salida) salida.textContent = dinamicoSinCodigo() ? 'La liga corta se asigna al guardar' : (texto || '—');
      if (avisoContraste) {
        const problema = problemaContraste(estado.color, estado.fondo);
        avisoContraste.hidden = !problema;
        $('span', avisoContraste).textContent = problema;
      }
    }

    // Un dinámico redirige a una página: solo Liga y WhatsApp.
    function ajustarTiposPorModo() {
      const soloPagina = esDinamico();
      $$('[data-solo-estatico]', form).forEach(l => {
        const r = l.querySelector('input');
        r.disabled = soloPagina || r.closest('fieldset:disabled') !== null;
        l.classList.toggle('no-disponible', soloPagina);
      });
      const nota = $('[data-nota-dinamico]', form);
      if (nota) nota.hidden = !soloPagina;
      const actual = form.querySelector('[name="tipo_contenido"]:checked');
      if (soloPagina && actual && actual.closest('[data-solo-estatico]')) {
        const url = form.querySelector('[name="tipo_contenido"][value="url"]');
        url.checked = true;
        return 'url';
      }
      return null;
    }
    $$('[name="tipo"]', form).forEach(r => r.addEventListener('change', () => { const t = ajustarTiposPorModo(); if (t) mostrarTipo(t); }));

    function mostrarTipo(nuevo) {
      tipo = nuevo;
      $$('[data-panel-tipo]', form).forEach(p => { p.hidden = p.dataset.panelTipo !== tipo; });
      $$('[data-tipo-boton]', form).forEach(b => b.setAttribute('aria-selected', String(b.dataset.tipoBoton === tipo)));
      actualizar();
    }
    $$('[data-tipo-boton]', form).forEach(b => b.addEventListener('click', () => mostrarTipo(b.dataset.tipoBoton)));
    $$('[name="tipo_contenido"]', form).forEach(r => r.addEventListener('change', () => mostrarTipo(r.value)));

    form.addEventListener('input', debounce(() => actualizar(), 250));
    form.addEventListener('change', () => actualizar(false));
    form.addEventListener('submit', ev => {
      if (form.dataset.simular !== undefined) {
        ev.preventDefault();
        notificar(form.dataset.simular || 'Guardado');
      }
    });

    // Logo: se dibuja dentro del QR (y por eso sale en las descargas)
    function usarLogo(dataUrl) {
      const img = new Image();
      img.onload = () => { estado.logo = img; estado.logoUrl = dataUrl; actualizar(false); };
      img.onerror = () => notificar('No se pudo leer esa imagen');
      img.src = dataUrl;
    }
    let logoGuardado = '';
    if (form.dataset.logoUrl) {
      // Logo ya guardado: se convierte en data URL para poder incrustarlo también en el SVG.
      fetch(form.dataset.logoUrl).then(r => r.blob()).then(b => {
        const lector = new FileReader();
        lector.onload = () => { logoGuardado = lector.result; usarLogo(logoGuardado); };
        lector.readAsDataURL(b);
      }).catch(() => {});
    }
    const quitarLogo = form.querySelector('[data-quitar-logo]');
    if (quitarLogo) {
      quitarLogo.addEventListener('change', () => {
        if (quitarLogo.checked) { estado.logo = null; estado.logoUrl = ''; actualizar(false); }
        else if (logoGuardado) usarLogo(logoGuardado);
      });
    }
    const archivoLogo = form.querySelector('[data-archivo-logo]');
    if (archivoLogo) {
      archivoLogo.addEventListener('change', () => {
        const f = archivoLogo.files[0];
        if (!f) { if (logoGuardado && !(quitarLogo && quitarLogo.checked)) usarLogo(logoGuardado); else { estado.logo = null; estado.logoUrl = ''; actualizar(false); } return; }
        if (f.size > 1048576) { notificar('El logo pesa más de 1 MB'); archivoLogo.value = ''; return; }
        const lector = new FileReader();
        lector.onload = () => usarLogo(lector.result);
        lector.readAsDataURL(f);
      });
    }

    /** Matriz lista para descargar, o null (con aviso) si todavía no se puede. */
    function paraDescargar() {
      if (dinamicoSinCodigo()) {
        notificar('Guarda el QR primero: así obtiene su liga corta y el código impreso sí funcionará.');
        return null;
      }
      try {
        return matrizQR(estado.texto, !!estado.logo);
      } catch (e) {
        notificar('El contenido es demasiado largo para un QR');
        return null;
      }
    }
    const nombre = b => nombreArchivo((form.querySelector('[name="nombre"]') || {}).value || b.dataset.descargarPng || b.dataset.nombre);
    const marca = form.dataset.marca || '';

    $$('[data-descargar-png]', form).forEach(b => b.addEventListener('click', () => {
      const qr = paraDescargar();
      if (!qr) return;
      const canvas = document.createElement('canvas');
      dibujarQR(canvas, qr, { px: 1024, color: estado.color, fondo: estado.fondo, logo: estado.logo, marca });
      canvas.toBlob(blob => { descargarArchivo(blob, nombre(b) + '.png'); notificar('Descargaste tu QR en PNG'); }, 'image/png');
    }));
    $$('[data-descargar-svg]', form).forEach(b => b.addEventListener('click', () => {
      const qr = paraDescargar();
      if (!qr) return;
      const svg = svgQR(qr, { color: estado.color, fondo: estado.fondo, logoUrl: estado.logoUrl, marca });
      descargarArchivo(new Blob([svg], { type: 'image/svg+xml' }), nombre(b) + '.svg');
      notificar('Descargaste tu QR en SVG');
    }));
    $$('[data-descargar-pdf]', form).forEach(b => b.addEventListener('click', () => {
      const qr = paraDescargar();
      if (!qr) return;
      const canvas = document.createElement('canvas');
      dibujarQR(canvas, qr, { px: 1200, color: estado.color, fondo: estado.fondo, logo: estado.logo, marca });
      descargarArchivo(pdfDeCanvas(canvas), nombre(b) + '.pdf');
      notificar('Descargaste tu QR en PDF');
    }));

    tipo = ajustarTiposPorModo() || tipo;
    mostrarTipo(tipo);
  });

  /* --- Descarga directa desde "Mis QR" (con el diseño y logo guardados) --- */
  $$('[data-descargar-qr]').forEach(b => b.addEventListener('click', async () => {
    const d = JSON.parse(b.dataset.descargarQr);
    let logo = null;
    if (d.logo) {
      logo = await new Promise(res => { const img = new Image(); img.onload = () => res(img); img.onerror = () => res(null); img.src = d.logo; });
    }
    let qr;
    try { qr = matrizQR(d.texto, !!logo); } catch (e) { return notificar('No se pudo generar el QR'); }
    if (!qr) return notificar('No se pudo cargar el generador de QR; revisa tu conexión');
    const canvas = document.createElement('canvas');
    dibujarQR(canvas, qr, { px: 1024, color: d.color, fondo: d.fondo, logo, marca: d.marca || '' });
    canvas.toBlob(blob => { descargarArchivo(blob, nombreArchivo(d.nombre) + '.png'); notificar('Descargaste «' + d.nombre + '» en PNG'); }, 'image/png');
  }));

  /* ---------- Precios mensual / anual ---------- */
  $$('[data-periodo]').forEach(b => b.addEventListener('click', () => {
    const periodo = b.dataset.periodo;
    $$('[data-periodo]').forEach(x => x.setAttribute('aria-selected', String(x === b)));
    $$('[data-mensual]').forEach(el => {
      el.querySelector('[data-monto]').textContent = periodo === 'anual' ? el.dataset.anualMes : el.dataset.mensual;
      const ahorro = el.querySelector('[data-ahorro]');
      if (ahorro) ahorro.textContent = periodo === 'anual' ? el.dataset.ahorro : '';
    });
  }));

  /* ---------- Filtros de tablas ---------- */
  $$('[data-tabla-filtrable]').forEach(contenedor => {
    const filas = $$('[data-fila]', contenedor);
    const buscador = $('[data-buscar]', contenedor);
    const contador = $('[data-contador]', contenedor);
    const vacio = $('[data-sin-resultados]', contenedor);
    let filtro = '';

    function aplicar() {
      const q = (buscador ? buscador.value : '').trim().toLowerCase();
      let visibles = 0;
      filas.forEach(f => {
        const coincideTexto = !q || f.textContent.toLowerCase().includes(q);
        const coincideFiltro = !filtro || (' ' + (f.dataset.etiquetas || '') + ' ').includes(' ' + filtro + ' ');
        const ver = coincideTexto && coincideFiltro;
        f.hidden = !ver;
        if (ver) visibles++;
      });
      if (contador) contador.textContent = visibles + (visibles === 1 ? ' resultado' : ' resultados');
      if (vacio) vacio.hidden = visibles > 0;
    }

    if (buscador) buscador.addEventListener('input', aplicar);
    $$('[data-filtro]', contenedor).forEach(b => b.addEventListener('click', () => {
      filtro = b.dataset.filtro;
      $$('[data-filtro]', contenedor).forEach(x => x.setAttribute('aria-selected', String(x === b)));
      aplicar();
    }));
    // Filtro inicial desde la URL (?filtro=de-pago)
    const inicial = new URLSearchParams(location.search).get('filtro');
    const botonInicial = inicial && $('[data-filtro="' + inicial + '"]', contenedor);
    if (botonInicial) botonInicial.click(); else aplicar();
  });

  /* ---------- Exportar CSV (solo filas visibles) ---------- */
  $$('[data-exportar-csv]').forEach(b => b.addEventListener('click', () => {
    const tabla = document.getElementById(b.dataset.exportarCsv);
    const encabezados = $$('thead th[data-csv]', tabla).map(th => th.dataset.csv);
    const filas = $$('tbody tr[data-fila]', tabla).filter(f => !f.hidden);
    const lineas = [encabezados].concat(filas.map(f => encabezados.map(h => f.dataset['csv' + h.charAt(0).toUpperCase() + h.slice(1)] || '')));
    const csv = '﻿' + lineas.map(l => l.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\r\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    a.download = (b.dataset.nombre || 'exportacion') + '.csv';
    a.click();
    notificar('Exportaste ' + filas.length + ' filas a CSV');
  }));

  /* ---------- Copiar al portapapeles ---------- */
  $$('[data-copiar]').forEach(b => b.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(b.dataset.copiar);
      notificar(b.dataset.copiarMensaje || 'Copiado');
    } catch (e) {
      notificar('No se pudo copiar; selecciona el texto manualmente');
    }
  }));

  /* ---------- Acciones con confirmación ---------- */
  // Si el botón envía un formulario real, al confirmar se envía; si no, la acción es simulada.
  const dialogo = document.getElementById('dialogo-confirmar');
  $$('[data-confirmar]').forEach(b => b.addEventListener('click', ev => {
    ev.preventDefault();
    const enviaFormulario = b.form && b.type === 'submit';
    const ejecutar = () => enviaFormulario ? b.form.requestSubmit(b) : notificar(b.dataset.hecho || 'Listo');
    if (!dialogo) return ejecutar();
    $('[data-dialogo-titulo]', dialogo).textContent = b.dataset.confirmar;
    $('[data-dialogo-texto]', dialogo).textContent = b.dataset.detalle || '';
    const ok = $('[data-dialogo-ok]', dialogo);
    ok.textContent = b.dataset.accion || 'Confirmar';
    ok.className = 'boton ' + (b.dataset.peligro !== undefined ? 'boton-peligro' : 'boton-tinta');
    ok.onclick = () => { dialogo.close(); ejecutar(); };
    dialogo.showModal();
  }));
  $$('[data-dialogo-cancelar]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));

  // Acciones directas simuladas (sin confirmación)
  $$('[data-simular-accion]').forEach(b => b.addEventListener('click', ev => {
    ev.preventDefault();
    notificar(b.dataset.simularAccion);
  }));

  // Formularios simulados
  $$('form[data-simular]:not([data-generador])').forEach(f => f.addEventListener('submit', ev => {
    ev.preventDefault();
    notificar(f.dataset.simular || 'Guardado');
    if (f.dataset.ir) setTimeout(() => { location.href = f.dataset.ir; }, 700);
  }));

  /* ---------- Gráficas (Chart.js) ---------- */
  // Los colores salen de las variables CSS del tema; se vuelven a pintar al cambiar de tema.
  if (typeof Chart !== 'undefined') {
    const graficas = [];
    const pintarGraficas = () => {
      graficas.splice(0).forEach(g => g.destroy());
      const css = getComputedStyle(raiz);
      const c = n => css.getPropertyValue(n).trim();
      Chart.defaults.font.family = c('--fuente');
      Chart.defaults.font.size = 12;
      Chart.defaults.color = c('--gris');
      const paleta = [c('--tinta'), c('--rosa'), c('--serie-3'), c('--serie-4'), c('--verde')];
      const transparente = color => color.length === 7 ? color + '14' : color;

      $$('canvas[data-grafica]').forEach(lienzo => {
        const d = JSON.parse(lienzo.dataset.grafica);
        const linea = d.tipo === 'line';
        const dona = d.tipo === 'doughnut';
        graficas.push(new Chart(lienzo, {
          type: d.tipo,
          data: {
            labels: d.etiquetas,
            datasets: d.series.map((s, i) => ({
              label: s.nombre,
              data: s.datos,
              borderColor: dona ? c('--blanco') : paleta[i],
              backgroundColor: dona ? paleta : (linea ? transparente(paleta[i]) : paleta[i]),
              borderWidth: linea ? 2 : (dona ? 2 : 0),
              fill: linea && i === 0,
              tension: .3,
              pointRadius: 0,
              pointHoverRadius: 4,
              borderRadius: dona ? 0 : 4,
              maxBarThickness: 28,
            })),
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            cutout: dona ? '68%' : undefined,
            plugins: {
              legend: { display: d.series.length > 1 || dona, position: dona ? 'right' : 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
              tooltip: {
                backgroundColor: c('--oscuro'), titleColor: c('--sobre-oscuro'), bodyColor: c('--sobre-oscuro'),
                padding: 10, cornerRadius: 8, displayColors: d.series.length > 1,
                callbacks: d.moneda ? { label: ctx => ' ' + ctx.dataset.label + ': $' + Number(ctx.parsed.y).toLocaleString('es-MX') } : {},
              },
            },
            scales: dona ? {} : {
              x: { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
              y: { grid: { color: c('--linea') }, border: { display: false }, beginAtZero: true, ticks: { maxTicksLimit: 5, callback: v => (d.moneda ? '$' : '') + Number(v).toLocaleString('es-MX') } },
            },
          },
        }));
      });
    };
    pintarGraficas();
    document.addEventListener('tema-cambiado', pintarGraficas);
  }

  /* ---------- Vista previa de colores en Configuración ---------- */
  // Mientras el admin elige colores, se aplican al tema que está viendo (sin guardar).
  $$('input[data-color-variable]').forEach(entrada => entrada.addEventListener('input', () => {
    const delTemaOscuro = entrada.dataset.colorTema === 'oscuro';
    if (delTemaOscuro === (raiz.dataset.tema === 'oscuro')) {
      raiz.style.setProperty('--' + entrada.dataset.colorVariable, entrada.value);
    }
  }));
  document.addEventListener('tema-cambiado', () => {
    raiz.style.cssText = '';
    $$('input[data-color-variable]').forEach(entrada => entrada.dispatchEvent(new Event('input')));
  });
})();
