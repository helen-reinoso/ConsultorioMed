/**
 * app.js — Sistema de Reservas Médicas
 * Consume la API REST propia (api/index.php) mediante fetch/AJAX.
 * Renderiza el "osciloscopio de disponibilidad" y gestiona el flujo
 * de agendamiento con validación de traslapes en tiempo real.
 */

const API_BASE = '../../api/index.php';

// ---------------------------------------------------------------------
// Utilidades de red
// ---------------------------------------------------------------------
async function apiFetch(path, options = {}) {
    const res = await fetch(`${API_BASE}${path}`, {
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        ...options,
    });
    let data;
    try {
        data = await res.json();
    } catch {
        data = { exito: false, mensaje: 'Respuesta inválida del servidor.' };
    }
    return { status: res.status, ...data };
}

function mostrarToast(mensaje, tipo = 'ok', titulo = null) {
    const cont = document.getElementById('toast-container');
    if (!cont) return;
    const el = document.createElement('div');
    el.className = `toast ${tipo === 'error' ? 'error' : ''}`;
    el.innerHTML = `<div class="toast-title">${titulo || (tipo === 'error' ? 'Error' : 'Notificación')}</div>${mensaje}`;
    cont.appendChild(el);
    setTimeout(() => el.remove(), 6000);
}

// ---------------------------------------------------------------------
// AGENDAMIENTO DE CITAS (paciente)
// ---------------------------------------------------------------------
const estadoAgendamiento = {
    idMedico: null,
    fecha: null,
    slotSeleccionado: null, // { inicio, fin }
};

function inicializarSelectorMedicos() {
    const tarjetas = document.querySelectorAll('.card-medico');
    tarjetas.forEach((tarjeta) => {
        tarjeta.addEventListener('click', () => {
            tarjetas.forEach((t) => t.classList.remove('selected'));
            tarjeta.classList.add('selected');
            estadoAgendamiento.idMedico = tarjeta.dataset.idMedico;
            estadoAgendamiento.slotSeleccionado = null;
            const fechaInput = document.getElementById('input-fecha');
            if (fechaInput && fechaInput.value) {
                cargarOsciloscopio();
            }
        });
    });
}

function inicializarSelectorFecha() {
    const fechaInput = document.getElementById('input-fecha');
    if (!fechaInput) return;
    fechaInput.min = new Date().toISOString().split('T')[0];
    fechaInput.addEventListener('change', () => {
        estadoAgendamiento.fecha = fechaInput.value;
        estadoAgendamiento.slotSeleccionado = null;
        if (estadoAgendamiento.idMedico) cargarOsciloscopio();
    });
}

/** Genera bloques de 30 minutos entre hora_inicio y hora_fin */
function generarBloques(horaInicio, horaFin, minutos = 30) {
    const bloques = [];
    let [h, m] = horaInicio.split(':').map(Number);
    const [hf, mf] = horaFin.split(':').map(Number);
    while (h < hf || (h === hf && m < mf)) {
        const inicio = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
        m += minutos;
        if (m >= 60) { m -= 60; h += 1; }
        const fin = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
        bloques.push({ inicio, fin });
    }
    return bloques;
}

function seSuperponen(aInicio, aFin, bInicio, bFin) {
    return aInicio < bFin && aFin > bInicio;
}

async function cargarOsciloscopio() {
    const cont = document.getElementById('osciloscopio');
    const wrapper = document.getElementById('osciloscopio-wrapper');
    if (!cont || !estadoAgendamiento.idMedico || !estadoAgendamiento.fecha) return;

    cont.innerHTML = '<span style="color:var(--text-muted);font-size:0.8rem;">Escaneando agenda del médico...</span>';
    wrapper.style.display = 'block';

    const resp = await apiFetch(`/medicos/${estadoAgendamiento.idMedico}/disponibilidad?fecha=${estadoAgendamiento.fecha}`);

    if (!resp.exito || !resp.datos.horario_laboral.length) {
        cont.innerHTML = '<span style="color:var(--danger);font-size:0.8rem;">El médico no labora ese día.</span>';
        return;
    }

    cont.innerHTML = '';
    resp.datos.horario_laboral.forEach((bloqueLaboral) => {
        const bloques = generarBloques(bloqueLaboral.hora_inicio.slice(0, 5), bloqueLaboral.hora_fin.slice(0, 5));
        bloques.forEach((b) => {
            const ocupado = resp.datos.citas_ocupadas.some((c) =>
                seSuperponen(b.inicio, b.fin, c.hora_inicio.slice(0, 5), c.hora_fin.slice(0, 5))
            );
            const div = document.createElement('div');
            div.className = `osc-slot ${ocupado ? 'ocupado' : 'libre'}`;
            div.title = `${b.inicio} - ${b.fin}${ocupado ? ' (ocupado)' : ' (disponible)'}`;
            div.dataset.inicio = b.inicio;
            div.dataset.fin = b.fin;
            if (!ocupado) {
                div.addEventListener('click', () => seleccionarSlot(div, b));
            }
            cont.appendChild(div);
        });
    });
}

function seleccionarSlot(elemento, bloque) {
    document.querySelectorAll('.osc-slot').forEach((s) => s.classList.remove('seleccionado'));
    elemento.classList.add('seleccionado');
    estadoAgendamiento.slotSeleccionado = bloque;
    document.getElementById('resumen-slot').textContent = `Horario seleccionado: ${bloque.inicio} - ${bloque.fin}`;
    document.getElementById('btn-confirmar-cita').disabled = false;
}

async function confirmarCita(evento) {
    evento.preventDefault();
    const btn = document.getElementById('btn-confirmar-cita');
    const motivo = document.getElementById('input-motivo').value;

    if (!estadoAgendamiento.idMedico || !estadoAgendamiento.fecha || !estadoAgendamiento.slotSeleccionado) {
        mostrarToast('Seleccione médico, fecha y un horario disponible.', 'error');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Validando disponibilidad...';

    // RETO: primero se revalida en tiempo real contra la API antes de enviar
    const verificacion = await apiFetch('/citas/verificar-disponibilidad', {
        method: 'POST',
        body: JSON.stringify({
            id_medico: estadoAgendamiento.idMedico,
            fecha: estadoAgendamiento.fecha,
            hora_inicio: estadoAgendamiento.slotSeleccionado.inicio,
            hora_fin: estadoAgendamiento.slotSeleccionado.fin,
        }),
    });

    if (!verificacion.disponible) {
        mostrarToast(verificacion.mensaje || 'Ese horario ya no está disponible.', 'error', 'Traslape detectado');
        btn.textContent = 'Confirmar cita';
        cargarOsciloscopio(); // refresca para reflejar el estado real
        return;
    }

    const registro = await apiFetch('/citas', {
        method: 'POST',
        body: JSON.stringify({
            id_medico: estadoAgendamiento.idMedico,
            fecha: estadoAgendamiento.fecha,
            hora_inicio: estadoAgendamiento.slotSeleccionado.inicio,
            hora_fin: estadoAgendamiento.slotSeleccionado.fin,
            motivo,
        }),
    });

    if (registro.exito) {
        mostrarToast(`Cita registrada para el ${estadoAgendamiento.fecha} a las ${estadoAgendamiento.slotSeleccionado.inicio}. Se ha enviado una notificación por correo (simulada).`, 'ok', 'Cita confirmada');
        setTimeout(() => window.location.reload(), 1800);
    } else {
        mostrarToast(registro.mensaje || 'No fue posible registrar la cita.', 'error');
        btn.disabled = false;
        btn.textContent = 'Confirmar cita';
        cargarOsciloscopio();
    }
}

// ---------------------------------------------------------------------
// PANEL ADMINISTRATIVO: cambiar estado de citas
// ---------------------------------------------------------------------
async function cambiarEstadoCita(idCita, nuevoEstado, botonOrigen) {
    if (nuevoEstado === 'cancelada' && !confirm('¿Confirma que desea cancelar esta cita?')) return;

    botonOrigen.disabled = true;
    const res = await apiFetch(`/citas/${idCita}/estado`, {
        method: 'PATCH',
        body: JSON.stringify({ estado: nuevoEstado }),
    });

    if (res.exito) {
        mostrarToast(res.mensaje, 'ok', 'Estado actualizado');
        setTimeout(() => window.location.reload(), 1000);
    } else {
        mostrarToast(res.mensaje || 'No fue posible actualizar el estado.', 'error');
        botonOrigen.disabled = false;
    }
}

// =======================================================================
// MÓDULO DE ADMINISTRACIÓN: CRUD de médicos, horarios, especialidades
// y pacientes. Usa un modal genérico reutilizable.
// =======================================================================

function abrirModal(html) {
    const overlay = document.getElementById('modal-overlay');
    const box = document.getElementById('modal-box-content');
    if (!overlay || !box) return;
    box.innerHTML = html;
    overlay.classList.add('open');
}

function cerrarModal() {
    const overlay = document.getElementById('modal-overlay');
    if (overlay) overlay.classList.remove('open');
}

/** Construye las <option> de especialidades a partir del JSON embebido en la página */
function opcionesEspecialidades(seleccionadoId = null) {
    const especialidades = window.ESPECIALIDADES || [];
    return especialidades.map((e) =>
        `<option value="${e.id_especialidad}" ${String(e.id_especialidad) === String(seleccionadoId) ? 'selected' : ''}>${e.nombre}</option>`
    ).join('');
}

const DIAS_SEMANA = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

// ---------------------- MÉDICOS ----------------------
function mostrarFormMedico(medico = null) {
    const esEdicion = !!medico;
    abrirModal(`
        <h3>${esEdicion ? 'Editar médico' : 'Nuevo médico'}</h3>
        <form id="form-medico">
            <label>Nombre completo</label>
            <input type="text" name="nombre" required value="${medico ? medico.nombre.replace(/"/g, '&quot;') : ''}">
            <label>Especialidad</label>
            <select name="id_especialidad" required>${opcionesEspecialidades(medico ? medico.id_especialidad : null)}</select>
            <label>Correo electrónico</label>
            <input type="email" name="email" value="${medico ? (medico.email || '') : ''}">
            <label>Teléfono</label>
            <input type="text" name="telefono" value="${medico ? (medico.telefono || '') : ''}">
            ${!esEdicion ? `
            <label>Contraseña de acceso (opcional)</label>
            <input type="password" name="password" placeholder="Déjelo en blanco para no crear login todavía">
            <p style="font-size:0.76rem;color:var(--text-muted);margin-top:4px;">Si indica una contraseña, el médico podrá iniciar sesión con el correo de arriba y ver su propia agenda y pacientes.</p>
            ` : ''}
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-sm">${esEdicion ? 'Guardar cambios' : 'Crear médico'}</button>
            </div>
        </form>
    `);

    document.getElementById('form-medico').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.target);
        const payload = Object.fromEntries(fd.entries());
        const res = esEdicion
            ? await apiFetch(`/medicos/${medico.id_medico}`, { method: 'PUT', body: JSON.stringify(payload) })
            : await apiFetch('/medicos', { method: 'POST', body: JSON.stringify(payload) });

        if (res.exito) {
            mostrarToast(esEdicion ? 'Médico actualizado.' : 'Médico creado.', 'ok');
            cerrarModal();
            setTimeout(() => window.location.reload(), 700);
        } else {
            mostrarToast(res.mensaje || Object.values(res.errores || {})[0] || 'No fue posible guardar el médico.', 'error');
        }
    });
}

async function eliminarMedico(id, nombre) {
    if (!confirm(`¿Desactivar al médico "${nombre}"? Dejará de aparecer disponible para nuevas citas.`)) return;
    const res = await apiFetch(`/medicos/${id}`, { method: 'DELETE' });
    if (res.exito) {
        mostrarToast('Médico desactivado.', 'ok');
        setTimeout(() => window.location.reload(), 700);
    } else {
        mostrarToast(res.mensaje || 'No fue posible desactivar el médico.', 'error');
    }
}

function mostrarFormAccesoMedico(idMedico, nombreMedico) {
    abrirModal(`
        <h3>Crear acceso para ${nombreMedico}</h3>
        <p style="font-size:0.85rem;color:var(--text-muted);">El médico podrá iniciar sesión con estas credenciales y ver su propia agenda y pacientes.</p>
        <form id="form-acceso-medico">
            <label>Correo electrónico</label>
            <input type="email" name="email" required>
            <label>Contraseña</label>
            <input type="password" name="password" required placeholder="Mínimo 8 caracteres">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-sm">Crear acceso</button>
            </div>
        </form>
    `);
    document.getElementById('form-acceso-medico').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.target);
        const res = await apiFetch(`/medicos/${idMedico}/acceso`, { method: 'POST', body: JSON.stringify(Object.fromEntries(fd.entries())) });
        if (res.exito) {
            mostrarToast('Acceso creado. El médico ya puede iniciar sesión.', 'ok');
            cerrarModal();
            setTimeout(() => window.location.reload(), 700);
        } else {
            mostrarToast(Object.values(res.errores || {})[0] || res.mensaje || 'No fue posible crear el acceso.', 'error');
        }
    });
}

// ---------------------- HORARIOS ----------------------
async function mostrarGestionHorarios(idMedico, nombreMedico) {
    abrirModal(`<h3>Horarios de ${nombreMedico}</h3><div id="lista-horarios">Cargando...</div>`);
    await refrescarListaHorarios(idMedico, nombreMedico);
}

async function refrescarListaHorarios(idMedico, nombreMedico) {
    const resp = await apiFetch(`/medicos/${idMedico}/horarios`);
    const cont = document.getElementById('lista-horarios');
    if (!cont) return;

    const filas = (resp.datos || []).map((h) => `
        <div class="horario-row">
            <span>${DIAS_SEMANA[h.dia_semana]} · ${h.hora_inicio.slice(0,5)} - ${h.hora_fin.slice(0,5)}</span>
            <button class="btn btn-danger btn-sm" data-eliminar-horario="${h.id_horario}">Eliminar</button>
        </div>
    `).join('') || '<p style="color:var(--text-muted);font-size:0.85rem;">Este médico aún no tiene horarios registrados.</p>';

    cont.innerHTML = `
        ${filas}
        <form id="form-horario" style="margin-top:16px;border-top:1px solid var(--border-line);padding-top:16px;">
            <label>Día de la semana</label>
            <select name="dia_semana" required>
                ${DIAS_SEMANA.slice(1).map((d, i) => `<option value="${i+1}">${d}</option>`).join('')}
            </select>
            <label>Hora de inicio</label>
            <input type="time" name="hora_inicio" required>
            <label>Hora de fin</label>
            <input type="time" name="hora_fin" required>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="cerrarModal()">Cerrar</button>
                <button type="submit" class="btn btn-sm">Agregar horario</button>
            </div>
        </form>
    `;

    cont.querySelectorAll('[data-eliminar-horario]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('¿Eliminar este bloque de horario?')) return;
            const res = await apiFetch(`/medicos/${idMedico}/horarios/${btn.dataset.eliminarHorario}`, { method: 'DELETE' });
            if (res.exito) {
                mostrarToast('Horario eliminado.', 'ok');
                refrescarListaHorarios(idMedico, nombreMedico);
            } else {
                mostrarToast(res.mensaje || 'No fue posible eliminar el horario.', 'error');
            }
        });
    });

    document.getElementById('form-horario').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.target);
        const payload = Object.fromEntries(fd.entries());
        const res = await apiFetch(`/medicos/${idMedico}/horarios`, { method: 'POST', body: JSON.stringify(payload) });
        if (res.exito) {
            mostrarToast('Horario agregado.', 'ok');
            refrescarListaHorarios(idMedico, nombreMedico);
        } else {
            mostrarToast(res.mensaje || 'No fue posible agregar el horario.', 'error');
        }
    });
}

// ---------------------- ESPECIALIDADES ----------------------
function mostrarFormEspecialidad() {
    abrirModal(`
        <h3>Nueva especialidad</h3>
        <form id="form-especialidad">
            <label>Nombre</label>
            <input type="text" name="nombre" required>
            <label>Descripción (opcional)</label>
            <textarea name="descripcion" rows="2"></textarea>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-sm">Crear especialidad</button>
            </div>
        </form>
    `);
    document.getElementById('form-especialidad').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.target);
        const res = await apiFetch('/especialidades', { method: 'POST', body: JSON.stringify(Object.fromEntries(fd.entries())) });
        if (res.exito) {
            mostrarToast('Especialidad creada.', 'ok');
            cerrarModal();
            setTimeout(() => window.location.reload(), 700);
        } else {
            mostrarToast(res.mensaje || Object.values(res.errores || {})[0] || 'No fue posible crear la especialidad.', 'error');
        }
    });
}

async function eliminarEspecialidad(id, nombre) {
    if (!confirm(`¿Eliminar la especialidad "${nombre}"?`)) return;
    const res = await apiFetch(`/especialidades/${id}`, { method: 'DELETE' });
    if (res.exito) {
        mostrarToast('Especialidad eliminada.', 'ok');
        setTimeout(() => window.location.reload(), 700);
    } else {
        mostrarToast(res.mensaje || 'No fue posible eliminar la especialidad.', 'error');
    }
}

// ---------------------- PACIENTES ----------------------
function mostrarFormNuevoPaciente() {
    abrirModal(`
        <h3>Nuevo paciente</h3>
        <form id="form-nuevo-paciente">
            <label>Nombre completo</label>
            <input type="text" name="nombre" required>
            <label>Correo electrónico</label>
            <input type="email" name="email" required>
            <label>Contraseña temporal</label>
            <input type="password" name="password" required placeholder="Mínimo 8 caracteres, 1 mayúscula, 1 número">
            <label>Teléfono</label>
            <input type="text" name="telefono" placeholder="6000-0000">
            <label>Fecha de nacimiento</label>
            <input type="date" name="fecha_nacimiento">
            <label>Dirección</label>
            <input type="text" name="direccion">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-sm">Crear paciente</button>
            </div>
        </form>
    `);
    document.getElementById('form-nuevo-paciente').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.target);
        const res = await apiFetch('/pacientes', { method: 'POST', body: JSON.stringify(Object.fromEntries(fd.entries())) });
        if (res.exito) {
            mostrarToast('Paciente creado correctamente.', 'ok');
            cerrarModal();
            setTimeout(() => window.location.reload(), 700);
        } else {
            mostrarToast(Object.values(res.errores || {})[0] || res.mensaje || 'No fue posible crear el paciente.', 'error');
        }
    });
}

function mostrarFormPaciente(paciente) {
    abrirModal(`
        <h3>Editar paciente</h3>
        <form id="form-paciente">
            <label>Nombre completo</label>
            <input type="text" name="nombre" required value="${paciente.nombre.replace(/"/g, '&quot;')}">
            <label>Teléfono</label>
            <input type="text" name="telefono" value="${paciente.telefono || ''}">
            <label>Dirección</label>
            <input type="text" name="direccion" value="${paciente.direccion || ''}">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-sm">Guardar cambios</button>
            </div>
        </form>
    `);
    document.getElementById('form-paciente').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.target);
        const res = await apiFetch(`/pacientes/${paciente.id_paciente}`, { method: 'PUT', body: JSON.stringify(Object.fromEntries(fd.entries())) });
        if (res.exito) {
            mostrarToast('Paciente actualizado.', 'ok');
            cerrarModal();
            setTimeout(() => window.location.reload(), 700);
        } else {
            mostrarToast(res.mensaje || 'No fue posible actualizar el paciente.', 'error');
        }
    });
}

async function eliminarPaciente(id, nombre) {
    if (!confirm(`¿Eliminar al paciente "${nombre}"? Esto también eliminará su historial de citas. Esta acción no se puede deshacer.`)) return;
    const res = await apiFetch(`/pacientes/${id}`, { method: 'DELETE' });
    if (res.exito) {
        mostrarToast('Paciente eliminado.', 'ok');
        setTimeout(() => window.location.reload(), 700);
    } else {
        mostrarToast(res.mensaje || 'No fue posible eliminar el paciente.', 'error');
    }
}

// ---------------------- NUEVA CITA (desde el panel admin) ----------------------
const estadoCitaAdmin = { idMedico: null, fecha: null, slot: null };

function mostrarFormNuevaCita() {
    estadoCitaAdmin.idMedico = null;
    estadoCitaAdmin.fecha = null;
    estadoCitaAdmin.slot = null;

    const pacientes = window.PACIENTES || [];
    const medicos = window.MEDICOS || [];

    abrirModal(`
        <h3>Nueva cita</h3>
        <form id="form-nueva-cita-admin">
            <label>Paciente</label>
            <select id="admin-cita-paciente" name="id_paciente" required>
                <option value="">Seleccione un paciente...</option>
                ${pacientes.map((p) => `<option value="${p.id_paciente}">${p.nombre}</option>`).join('')}
            </select>
            <label>Médico</label>
            <select id="admin-cita-medico" required>
                <option value="">Seleccione un médico...</option>
                ${medicos.map((m) => `<option value="${m.id_medico}">${m.nombre} — ${m.especialidad}</option>`).join('')}
            </select>
            <label>Fecha</label>
            <input type="date" id="admin-cita-fecha" required>

            <div id="admin-cita-osc-wrapper" style="display:none;">
                <label>Horario disponible</label>
                <div id="admin-cita-osc" class="oscilloscope"></div>
                <div class="osc-legend">
                    <span class="legend-libre"><i></i> Disponible</span>
                    <span class="legend-ocupado"><i></i> Ocupado</span>
                </div>
                <p id="admin-cita-resumen" style="font-family:var(--font-mono);font-size:0.78rem;color:var(--accent-cyan);margin-top:8px;">Haga clic en un bloque cian para elegir el horario.</p>
            </div>

            <label>Motivo (opcional)</label>
            <textarea name="motivo" rows="2"></textarea>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" id="admin-cita-confirmar" class="btn btn-sm">Crear cita</button>
            </div>
        </form>
    `);

    const fechaInput = document.getElementById('admin-cita-fecha');
    fechaInput.min = new Date().toISOString().split('T')[0];

    document.getElementById('admin-cita-medico').addEventListener('change', (ev) => {
        estadoCitaAdmin.idMedico = ev.target.value;
        estadoCitaAdmin.slot = null;
        if (estadoCitaAdmin.idMedico && estadoCitaAdmin.fecha) cargarOsciloscopioAdminCita();
    });
    fechaInput.addEventListener('change', (ev) => {
        estadoCitaAdmin.fecha = ev.target.value;
        estadoCitaAdmin.slot = null;
        if (estadoCitaAdmin.idMedico && estadoCitaAdmin.fecha) cargarOsciloscopioAdminCita();
    });

    document.getElementById('form-nueva-cita-admin').addEventListener('submit', confirmarCitaAdmin);
}

async function cargarOsciloscopioAdminCita() {
    const cont = document.getElementById('admin-cita-osc');
    const wrapper = document.getElementById('admin-cita-osc-wrapper');
    wrapper.style.display = 'block';
    cont.innerHTML = '<span style="color:var(--text-muted);font-size:0.78rem;">Escaneando agenda...</span>';
    estadoCitaAdmin.slot = null;
    document.getElementById('admin-cita-resumen').textContent = 'Haga clic en un bloque cian para elegir el horario.';

    const resp = await apiFetch(`/medicos/${estadoCitaAdmin.idMedico}/disponibilidad?fecha=${estadoCitaAdmin.fecha}`);
    if (!resp.exito || !resp.datos.horario_laboral.length) {
        cont.innerHTML = '<span style="color:var(--danger);font-size:0.78rem;">El médico no labora ese día.</span>';
        return;
    }

    cont.innerHTML = '';
    resp.datos.horario_laboral.forEach((bloqueLaboral) => {
        const bloques = generarBloques(bloqueLaboral.hora_inicio.slice(0, 5), bloqueLaboral.hora_fin.slice(0, 5));
        bloques.forEach((b) => {
            const ocupado = resp.datos.citas_ocupadas.some((c) =>
                seSuperponen(b.inicio, b.fin, c.hora_inicio.slice(0, 5), c.hora_fin.slice(0, 5))
            );
            const div = document.createElement('div');
            div.className = `osc-slot ${ocupado ? 'ocupado' : 'libre'}`;
            div.title = `${b.inicio} - ${b.fin}`;
            if (!ocupado) {
                div.addEventListener('click', () => {
                    document.querySelectorAll('#admin-cita-osc .osc-slot').forEach((s) => s.classList.remove('seleccionado'));
                    div.classList.add('seleccionado');
                    estadoCitaAdmin.slot = b;
                    document.getElementById('admin-cita-resumen').textContent = `Horario seleccionado: ${b.inicio} - ${b.fin}`;
                    document.getElementById('admin-cita-confirmar').disabled = false;
                });
            }
            cont.appendChild(div);
        });
    });
}

async function confirmarCitaAdmin(ev) {
    ev.preventDefault();
    const idPaciente = document.getElementById('admin-cita-paciente').value;
    const motivo = ev.target.querySelector('textarea[name="motivo"]').value;
    const btn = document.getElementById('admin-cita-confirmar');

    if (!idPaciente || !estadoCitaAdmin.idMedico || !estadoCitaAdmin.fecha || !estadoCitaAdmin.slot) {
        mostrarToast('Complete paciente, médico, fecha y un horario disponible.', 'error');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Validando...';

    const verificacion = await apiFetch('/citas/verificar-disponibilidad', {
        method: 'POST',
        body: JSON.stringify({
            id_medico: estadoCitaAdmin.idMedico,
            fecha: estadoCitaAdmin.fecha,
            hora_inicio: estadoCitaAdmin.slot.inicio,
            hora_fin: estadoCitaAdmin.slot.fin,
        }),
    });

    if (!verificacion.disponible) {
        mostrarToast(verificacion.mensaje || 'Ese horario ya no está disponible.', 'error', 'Traslape detectado');
        btn.textContent = 'Crear cita';
        cargarOsciloscopioAdminCita();
        return;
    }

    const res = await apiFetch('/citas', {
        method: 'POST',
        body: JSON.stringify({
            id_paciente: idPaciente,
            id_medico: estadoCitaAdmin.idMedico,
            fecha: estadoCitaAdmin.fecha,
            hora_inicio: estadoCitaAdmin.slot.inicio,
            hora_fin: estadoCitaAdmin.slot.fin,
            motivo,
        }),
    });

    if (res.exito) {
        mostrarToast('Cita creada correctamente.', 'ok');
        cerrarModal();
        setTimeout(() => window.location.reload(), 700);
    } else {
        mostrarToast(res.mensaje || 'No fue posible crear la cita.', 'error');
        btn.disabled = false;
        btn.textContent = 'Crear cita';
    }
}

// =======================================================================
// HISTORIAL CLÍNICO: diagnósticos, recetas y referencias.
// El médico puede agregar y eliminar sus propios registros.
// El admin y el propio paciente pueden verlo en modo solo lectura.
// =======================================================================
const TIPO_REGISTRO_LABELS = { diagnostico: 'Diagnóstico', receta: 'Receta', referencia: 'Referencia' };

async function mostrarHistorialPaciente(idPaciente, nombrePaciente) {
    abrirModal(`<h3>Historial de ${nombrePaciente}</h3><div id="lista-registros" style="margin-top:14px;">Cargando...</div>`);
    await refrescarHistorial(idPaciente, nombrePaciente);
}

async function refrescarHistorial(idPaciente, nombrePaciente) {
    const resp = await apiFetch(`/registros/${idPaciente}`);
    const cont = document.getElementById('lista-registros');
    if (!cont) return;

    const esMedico = window.ROL_ACTUAL === 'medico';

    const filas = (resp.datos || []).map((r) => `
        <div class="horario-row" style="flex-direction:column;align-items:stretch;gap:6px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="badge badge-confirmada">${TIPO_REGISTRO_LABELS[r.tipo] || r.tipo}</span>
                <span style="color:var(--text-muted);font-size:0.72rem;font-family:var(--font-mono);">${r.creado_en} · Dr(a). ${r.medico_nombre}</span>
            </div>
            <div style="font-size:0.87rem;color:var(--text-primary);white-space:pre-wrap;">${String(r.contenido).replace(/</g, '&lt;')}</div>
            ${esMedico ? `<button class="btn btn-danger btn-sm" style="align-self:flex-end;" data-eliminar-registro="${r.id_registro}">Eliminar</button>` : ''}
        </div>
    `).join('') || '<p style="color:var(--text-muted);font-size:0.85rem;">Sin registros en el historial todavía.</p>';

    cont.innerHTML = `
        ${filas}
        ${esMedico ? `
        <form id="form-registro" style="margin-top:16px;border-top:1px solid var(--border-line);padding-top:16px;">
            <label>Tipo de registro</label>
            <select name="tipo" required>
                <option value="diagnostico">Diagnóstico</option>
                <option value="receta">Receta</option>
                <option value="referencia">Referencia</option>
            </select>
            <label>Detalle</label>
            <textarea name="contenido" rows="3" required placeholder="Escriba el diagnóstico, la receta o la referencia..."></textarea>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="cerrarModal()">Cerrar</button>
                <button type="submit" class="btn btn-sm">Agregar al historial</button>
            </div>
        </form>` : `
        <div class="modal-actions"><button type="button" class="btn btn-secondary btn-sm" onclick="cerrarModal()">Cerrar</button></div>
        `}
    `;

    cont.querySelectorAll('[data-eliminar-registro]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('¿Eliminar este registro del historial?')) return;
            const res = await apiFetch(`/registros/${btn.dataset.eliminarRegistro}`, { method: 'DELETE' });
            if (res.exito) {
                mostrarToast('Registro eliminado.', 'ok');
                refrescarHistorial(idPaciente, nombrePaciente);
            } else {
                mostrarToast(res.mensaje || 'No fue posible eliminar el registro.', 'error');
            }
        });
    });

    const form = document.getElementById('form-registro');
    if (form) {
        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const fd = new FormData(ev.target);
            const payload = Object.fromEntries(fd.entries());
            payload.id_paciente = idPaciente;
            const res = await apiFetch('/registros', { method: 'POST', body: JSON.stringify(payload) });
            if (res.exito) {
                mostrarToast('Registro agregado al historial.', 'ok');
                refrescarHistorial(idPaciente, nombrePaciente);
            } else {
                mostrarToast(res.mensaje || 'No fue posible agregar el registro.', 'error');
            }
        });
    }
}

// ---------------------------------------------------------------------
// Cerrar sesión
// ---------------------------------------------------------------------
async function cerrarSesion() {
    await apiFetch('/auth/logout', { method: 'POST' });
    window.location.href = '../auth/login.php';
}

document.addEventListener('DOMContentLoaded', () => {
    inicializarSelectorMedicos();
    inicializarSelectorFecha();

    const formCita = document.getElementById('form-cita');
    if (formCita) formCita.addEventListener('submit', confirmarCita);

    const btnLogout = document.getElementById('btn-logout');
    if (btnLogout) btnLogout.addEventListener('click', cerrarSesion);

    // ---- Administración: citas ----
    document.getElementById('btn-nueva-cita')?.addEventListener('click', () => mostrarFormNuevaCita());

    document.querySelectorAll('[data-cambiar-estado]').forEach((btn) => {
        btn.addEventListener('click', () => {
            cambiarEstadoCita(btn.dataset.idCita, btn.dataset.cambiarEstado, btn);
        });
    });

    // ---- Administración: médicos ----
    document.getElementById('btn-nuevo-medico')?.addEventListener('click', () => mostrarFormMedico(null));

    document.querySelectorAll('[data-editar-medico]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const medico = (window.MEDICOS || []).find((m) => String(m.id_medico) === btn.dataset.editarMedico);
            if (medico) mostrarFormMedico(medico);
        });
    });

    document.querySelectorAll('[data-eliminar-medico]').forEach((btn) => {
        btn.addEventListener('click', () => eliminarMedico(btn.dataset.eliminarMedico, btn.dataset.nombre));
    });

    document.querySelectorAll('[data-horarios-medico]').forEach((btn) => {
        btn.addEventListener('click', () => mostrarGestionHorarios(btn.dataset.horariosMedico, btn.dataset.nombre));
    });

    document.querySelectorAll('[data-crear-acceso-medico]').forEach((btn) => {
        btn.addEventListener('click', () => mostrarFormAccesoMedico(btn.dataset.crearAccesoMedico, btn.dataset.nombre));
    });

    // ---- Historial clínico (médico, admin y el propio paciente) ----
    document.querySelectorAll('[data-historial-paciente]').forEach((btn) => {
        btn.addEventListener('click', () => mostrarHistorialPaciente(btn.dataset.historialPaciente, btn.dataset.nombre));
    });

    // ---- Administración: especialidades ----
    document.getElementById('btn-nueva-especialidad')?.addEventListener('click', () => mostrarFormEspecialidad());

    document.querySelectorAll('[data-eliminar-especialidad]').forEach((btn) => {
        btn.addEventListener('click', () => eliminarEspecialidad(btn.dataset.eliminarEspecialidad, btn.dataset.nombre));
    });

    // ---- Administración: pacientes ----
    document.getElementById('btn-nuevo-paciente')?.addEventListener('click', () => mostrarFormNuevoPaciente());

    document.querySelectorAll('[data-editar-paciente]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const paciente = (window.PACIENTES || []).find((p) => String(p.id_paciente) === btn.dataset.editarPaciente);
            if (paciente) mostrarFormPaciente(paciente);
        });
    });

    document.querySelectorAll('[data-eliminar-paciente]').forEach((btn) => {
        btn.addEventListener('click', () => eliminarPaciente(btn.dataset.eliminarPaciente, btn.dataset.nombre));
    });

    // ---- Modal genérico: cerrar con la X o clic fuera de la caja ----
    document.getElementById('modal-close')?.addEventListener('click', cerrarModal);
    document.getElementById('modal-overlay')?.addEventListener('click', (ev) => {
        if (ev.target.id === 'modal-overlay') cerrarModal();
    });
    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') cerrarModal();
    });
});
