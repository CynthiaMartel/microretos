// Mi usuario (/mi-usuario → MiUsuario.vue): nombre, contraseña y, para docentes y admins de
// centro, «Módulos que imparto» (sirven para que te inviten a colaborar en encuentros).
export default {
  rutas: ['mi-usuario'],
  titulo: 'Mi usuario',
  pasos: [
    { el: 'usu-cabecera', titulo: 'Tu cuenta',
      texto: 'Aquí cambias tu nombre y tu contraseña y, si das clase, marcas los módulos que impartes.' },
    { el: 'usu-email', titulo: 'Tu correo de acceso',
      texto: 'Es tu identificador para entrar y no se puede cambiar desde aquí. Con el botón de la derecha lo copias.' },
    { el: 'usu-nombre', titulo: 'Tu nombre',
      texto: 'Es el nombre que ven tus compañeros y DuaLab en la plataforma. El alumnado y las empresas no lo usan para identificarte.' },
    { el: 'usu-password', titulo: 'Cambiar la contraseña',
      texto: 'Déjalo en blanco si no quieres cambiarla. Si escribes una nueva (mínimo 8 caracteres, con mayúscula, minúscula y número) te pedirá repetirla y se cerrará tu sesión en todos los dispositivos.' },
    { el: 'usu-guardar', titulo: 'Guardar cambios',
      texto: 'Guarda el nombre y, si la has escrito, la nueva contraseña. Si cambias la contraseña tendrás que volver a iniciar sesión.' },
    { el: 'usu-modulos', titulo: 'Módulos que imparto',
      texto: 'Marca los módulos que das y pulsa «Guardar módulos». Sirven para colaborar: solo te pueden invitar a los encuentros de compañeros de tu centro y familia con algún módulo en común. No limitan los módulos de tus retos ajustados.' },
  ],
}
