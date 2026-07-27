<?php
/**
 * api/index.php
 * Punto de entrada único de la API REST.
 * Todas las respuestas son JSON. Se usan verbos HTTP (GET, POST, PUT,
 * PATCH, DELETE) y códigos de estado estándar (200, 201, 400, 401,
 * 403, 404, 409, 422, 500).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/CitaController.php';
require_once __DIR__ . '/../controllers/MedicoController.php';
require_once __DIR__ . '/../controllers/PacienteController.php';
require_once __DIR__ . '/../controllers/RegistroMedicoController.php';
require_once __DIR__ . '/../models/Notificacion.php';

header('Content-Type: application/json; charset=utf-8');

// ---------------------------------------------------------------------
// Manejo estructurado de excepciones: cualquier error no controlado
// se traduce en una respuesta JSON 500, nunca en un stack trace HTML.
// ---------------------------------------------------------------------
set_exception_handler(function (Throwable $e) {
    error_log('Excepción no controlada: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error interno del servidor.']);
    exit;
});

function responder(array $payload, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function cuerpoJSON(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ---------------------------------------------------------------------
// Enrutamiento: se admite /api/index.php/recurso/... y, con
// mod_rewrite (.htaccess), /api/recurso/...
// ---------------------------------------------------------------------
$rutaCompleta = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rutaCompleta = preg_replace('#^/reservas-medicas/api(/index\.php)?#', '', $rutaCompleta);
$segmentos = array_values(array_filter(explode('/', trim($rutaCompleta, '/'))));
$metodo = $_SERVER['REQUEST_METHOD'];

$recurso = $segmentos[0] ?? '';
$idRecurso = $segmentos[1] ?? null;
$subRecurso = $segmentos[2] ?? null;
$subId = $segmentos[3] ?? null;

try {
    switch ($recurso) {

        // ============================= AUTH =============================
        case 'auth':
            $auth = new AuthController();
            $body = cuerpoJSON();

            if ($idRecurso === 'registro' && $metodo === 'POST') {
                $res = $auth->registrarPaciente($body);
                responder($res, $res['exito'] ? 201 : 422);
            }

            if ($idRecurso === 'login' && $metodo === 'POST') {
                $res = $auth->iniciarSesion($body['email'] ?? '', $body['password'] ?? '');
                responder($res, $res['exito'] ? 200 : 401);
            }

            if ($idRecurso === 'logout' && $metodo === 'POST') {
                $auth->cerrarSesion();
                responder(['exito' => true, 'mensaje' => 'Sesión cerrada.']);
            }

            if ($idRecurso === 'yo' && $metodo === 'GET') {
                responder(['exito' => true, 'usuario' => usuarioActual()]);
            }

            responder(['exito' => false, 'mensaje' => 'Ruta de autenticación no encontrada.'], 404);
            break;

        // ============================ MEDICOS ============================
        case 'medicos':
            $ctrl = new MedicoController();

            // GET /medicos  ó  GET /medicos/{id}  -> público (paciente necesita ver médicos)
            if ($metodo === 'GET' && $idRecurso === null) {
                responder(['exito' => true, 'datos' => $ctrl->listar()]);
            }

            if ($metodo === 'GET' && $idRecurso !== null && $subRecurso === 'horarios') {
                responder(['exito' => true, 'datos' => $ctrl->listarHorarios((int) $idRecurso)]);
            }

            if ($metodo === 'GET' && $idRecurso !== null && $subRecurso === 'disponibilidad') {
                $fecha = $_GET['fecha'] ?? null;
                if (!$fecha) {
                    responder(['exito' => false, 'mensaje' => 'Debe indicar el parámetro fecha=YYYY-MM-DD'], 400);
                }
                responder(['exito' => true, 'datos' => $ctrl->disponibilidadPorFecha((int) $idRecurso, $fecha)]);
            }

            if ($metodo === 'GET' && $idRecurso !== null) {
                $medico = $ctrl->obtener((int) $idRecurso);
                $medico ? responder(['exito' => true, 'datos' => $medico]) : responder(['exito' => false, 'mensaje' => 'Médico no encontrado.'], 404);
            }

            // A partir de aquí, solo administrador
            requerirRol('admin');
            $body = cuerpoJSON();

            if ($metodo === 'POST' && $idRecurso !== null && $subRecurso === 'horarios') {
                $res = $ctrl->agregarHorario((int) $idRecurso, $body);
                responder($res, $res['codigo']);
            }

            if ($metodo === 'POST' && $idRecurso !== null && $subRecurso === 'acceso') {
                $res = $ctrl->crearAcceso((int) $idRecurso, $body);
                responder($res, $res['codigo']);
            }

            if ($metodo === 'PUT' && $idRecurso !== null && $subRecurso === 'horarios' && $subId !== null) {
                $res = $ctrl->actualizarHorario((int) $subId, $body);
                responder($res, $res['codigo']);
            }

            if ($metodo === 'DELETE' && $idRecurso !== null && $subRecurso === 'horarios' && $subId !== null) {
                $res = $ctrl->eliminarHorario((int) $subId);
                responder($res, $res['codigo']);
            }

            if ($metodo === 'POST' && $idRecurso === null) {
                $res = $ctrl->crear($body);
                responder($res, $res['codigo']);
            }

            if ($metodo === 'PUT' && $idRecurso !== null && $subRecurso === null) {
                $res = $ctrl->actualizar((int) $idRecurso, $body);
                responder($res, $res['codigo']);
            }

            if ($metodo === 'DELETE' && $idRecurso !== null && $subRecurso === null) {
                $res = $ctrl->eliminar((int) $idRecurso);
                responder($res, $res['codigo']);
            }

            responder(['exito' => false, 'mensaje' => 'Operación no soportada sobre médicos.'], 405);
            break;

        // ========================= ESPECIALIDADES =========================
        case 'especialidades':
            $ctrl = new MedicoController();
            if ($metodo === 'GET') {
                responder(['exito' => true, 'datos' => $ctrl->listarEspecialidades()]);
            }

            requerirRol('admin');
            $body = cuerpoJSON();

            if ($metodo === 'POST') {
                $res = $ctrl->crearEspecialidad($body);
                responder($res, $res['codigo']);
            }

            if ($metodo === 'DELETE' && $idRecurso !== null) {
                $res = $ctrl->eliminarEspecialidad((int) $idRecurso);
                responder($res, $res['codigo']);
            }

            responder(['exito' => false, 'mensaje' => 'Método no soportado.'], 405);
            break;

        // ============================ PACIENTES ============================
        case 'pacientes':
            requerirRol('admin');
            $ctrl = new PacienteController();
            $body = cuerpoJSON();

            if ($metodo === 'GET' && $idRecurso === null) {
                responder(['exito' => true, 'datos' => $ctrl->listar()]);
            }
            if ($metodo === 'GET' && $idRecurso !== null) {
                $p = $ctrl->obtener((int) $idRecurso);
                $p ? responder(['exito' => true, 'datos' => $p]) : responder(['exito' => false, 'mensaje' => 'No encontrado.'], 404);
            }
            if ($metodo === 'POST' && $idRecurso === null) {
                $auth = new AuthController();
                $res = $auth->registrarPaciente($body);
                responder($res, $res['exito'] ? 201 : 422);
            }
            if ($metodo === 'PUT' && $idRecurso !== null) {
                $res = $ctrl->actualizar((int) $idRecurso, $body);
                responder($res, $res['codigo']);
            }
            if ($metodo === 'DELETE' && $idRecurso !== null) {
                $res = $ctrl->eliminar((int) $idRecurso);
                responder($res, $res['codigo']);
            }
            responder(['exito' => false, 'mensaje' => 'Operación no soportada.'], 405);
            break;

        // ============================== CITAS ==============================
        case 'citas':
            if (!usuarioAutenticado()) {
                responder(['exito' => false, 'mensaje' => 'Debe iniciar sesión.'], 401);
            }
            $ctrl = new CitaController();
            $body = cuerpoJSON();
            $usuario = usuarioActual();

            // Verificación de disponibilidad EN TIEMPO REAL (no crea la cita)
            if ($metodo === 'POST' && $idRecurso === 'verificar-disponibilidad') {
                $res = $ctrl->verificarDisponibilidad($body);
                responder($res, $res['exito'] ? 200 : 422);
            }

            if ($metodo === 'GET' && $idRecurso === null) {
                if ($usuario['rol'] === 'admin') {
                    responder(['exito' => true, 'datos' => $ctrl->listarTodas()]);
                }
                if ($usuario['rol'] === 'medico') {
                    responder(['exito' => true, 'datos' => $ctrl->listarPorMedico((int) $usuario['id_medico'])]);
                }
                responder(['exito' => true, 'datos' => $ctrl->listarPorPaciente((int) $usuario['id_paciente'])]);
            }

            if ($metodo === 'GET' && $idRecurso !== null) {
                $cita = $ctrl->obtenerPorId((int) $idRecurso);
                if (!$cita) {
                    responder(['exito' => false, 'mensaje' => 'Cita no encontrada.'], 404);
                }
                // Un paciente solo puede ver sus propias citas; un médico, las suyas
                if ($usuario['rol'] === 'paciente' && (int) $cita['id_paciente'] !== (int) $usuario['id_paciente']) {
                    responder(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
                }
                if ($usuario['rol'] === 'medico' && (int) $cita['id_medico'] !== (int) $usuario['id_medico']) {
                    responder(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
                }
                responder(['exito' => true, 'datos' => $cita]);
            }

            if ($metodo === 'POST' && $idRecurso === null) {
                requerirRol('paciente', 'admin');
                if ($usuario['rol'] === 'admin') {
                    if (empty($body['id_paciente'])) {
                        responder(['exito' => false, 'mensaje' => 'Debe indicar el paciente para la cita.'], 422);
                    }
                    $idPacienteDestino = (int) $body['id_paciente'];
                } else {
                    $idPacienteDestino = (int) $usuario['id_paciente'];
                }
                $res = $ctrl->registrarCita($idPacienteDestino, $body);
                responder($res, $res['codigo']);
            }

            if ($metodo === 'PUT' && $idRecurso !== null) {
                // paciente reprograma su propia cita, o admin reprograma cualquiera
                $cita = $ctrl->obtenerPorId((int) $idRecurso);
                if (!$cita) {
                    responder(['exito' => false, 'mensaje' => 'Cita no encontrada.'], 404);
                }
                if ($usuario['rol'] === 'paciente' && (int) $cita['id_paciente'] !== (int) $usuario['id_paciente']) {
                    responder(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
                }
                $res = $ctrl->modificarCita((int) $idRecurso, $body);
                responder($res, $res['codigo']);
            }

            if ($metodo === 'PATCH' && $idRecurso !== null && $subRecurso === 'estado') {
                requerirRol('admin', 'paciente', 'medico');
                $cita = $ctrl->obtenerPorId((int) $idRecurso);
                if (!$cita) {
                    responder(['exito' => false, 'mensaje' => 'Cita no encontrada.'], 404);
                }
                $nuevoEstado = $body['estado'] ?? '';
                if ($usuario['rol'] === 'paciente') {
                    if ((int) $cita['id_paciente'] !== (int) $usuario['id_paciente'] || $nuevoEstado !== 'cancelada') {
                        responder(['exito' => false, 'mensaje' => 'Un paciente solo puede cancelar sus propias citas.'], 403);
                    }
                }
                if ($usuario['rol'] === 'medico' && (int) $cita['id_medico'] !== (int) $usuario['id_medico']) {
                    responder(['exito' => false, 'mensaje' => 'Solo puede gestionar sus propias citas.'], 403);
                }
                $res = $ctrl->cambiarEstado((int) $idRecurso, $nuevoEstado);
                responder($res, $res['codigo']);
            }

            if ($metodo === 'DELETE' && $idRecurso !== null) {
                requerirRol('admin');
                $ok = $ctrl->eliminar((int) $idRecurso);
                responder(['exito' => $ok], $ok ? 200 : 400);
            }

            responder(['exito' => false, 'mensaje' => 'Operación no soportada sobre citas.'], 405);
            break;

        // ========================== NOTIFICACIONES ==========================
        case 'notificaciones':
            if (!usuarioAutenticado()) {
                responder(['exito' => false, 'mensaje' => 'Debe iniciar sesión.'], 401);
            }
            if ($metodo === 'GET' && $idRecurso !== null) {
                $notifModel = new Notificacion();
                responder(['exito' => true, 'datos' => $notifModel->listarPorCita((int) $idRecurso)]);
            }
            responder(['exito' => false, 'mensaje' => 'Operación no soportada.'], 405);
            break;

        // ============================== MÉDICO (panel propio) ==============================
        case 'medico':
            requerirRol('medico');
            $usuario = usuarioActual();

            if ($metodo === 'GET' && $idRecurso === 'pacientes') {
                $ctrl = new MedicoController();
                responder(['exito' => true, 'datos' => $ctrl->pacientesPorMedico((int) $usuario['id_medico'])]);
            }

            responder(['exito' => false, 'mensaje' => 'Ruta no encontrada.'], 404);
            break;

        // ===================== REGISTROS MÉDICOS (historial clínico) =====================
        case 'registros':
            if (!usuarioAutenticado()) {
                responder(['exito' => false, 'mensaje' => 'Debe iniciar sesión.'], 401);
            }
            requerirRol('medico', 'admin', 'paciente');
            $registroCtrl = new RegistroMedicoController();
            $usuario = usuarioActual();
            $body = cuerpoJSON();

            // GET /registros/{id_paciente} — historial de un paciente
            if ($metodo === 'GET' && $idRecurso !== null) {
                if ($usuario['rol'] === 'paciente' && (int) $idRecurso !== (int) $usuario['id_paciente']) {
                    responder(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
                }
                responder(['exito' => true, 'datos' => $registroCtrl->listarPorPaciente((int) $idRecurso)]);
            }

            if ($metodo === 'POST' && $idRecurso === null) {
                requerirRol('medico');
                $res = $registroCtrl->crear((int) $usuario['id_medico'], $body);
                responder($res, $res['codigo']);
            }

            if ($metodo === 'DELETE' && $idRecurso !== null) {
                requerirRol('medico', 'admin');
                $res = $registroCtrl->eliminar((int) $idRecurso, $usuario);
                responder($res, $res['codigo']);
            }

            responder(['exito' => false, 'mensaje' => 'Operación no soportada.'], 405);
            break;

        default:
            responder(['exito' => false, 'mensaje' => 'Recurso no encontrado.'], 404);
    }
} catch (PDOException $e) {
    error_log('Error de base de datos: ' . $e->getMessage());
    responder(['exito' => false, 'mensaje' => 'Error al procesar la solicitud en la base de datos.'], 500);
}
