const usuarios = [
    {
        id: 0,
        nombre: "Administrador del Sistema",
        roles: ["admin"]
    },
    {
        id: 1,
        nombre: "Encargado General de Producción ",
        roles: ["ingeniero"]
    },
    {
        id: 2,
        nombre: "Gerente de Producción de Laboratorio",
        roles: ["medico"]
    },
    {
        id: 3,
        nombre: "Responsable de Producción de Medios de Cultivo",
        roles: ["operario_medios"]
    },
    {
        id: 4,
        nombre: "Responsable de Registro y Reporte de Siembra",
        roles: ["reporte_siembra"]
    },
    {
        id: 5,
        nombre: "Encargado de Incubadora y Suministro de Material",
        roles: ["incubadora"]
    },
    {
        id: 6,
        nombre: "Encargado de Organización y Limpieza de Incubadora",
        roles: ["limpieza_incubadora"]
    },
    {
        id: 7,
        nombre: "Operadora - María",
        roles: ["operadora_cultivo"],
        nivel: 1 // Etapa 3 (operadoras nuevas)
    },
    {
        id: 8,
        nombre: "Operadora de cultivo",
        roles: ["operadora_cultivo"],
        nivel: 2 // Etapa 2 (operadoras con más experiencia)
    },
    {
        id: 9,
        nombre: "Operadora de Lavado - Laura",
        roles: ["operadora_lavado"]
    },
    {
        id: 10,
        nombre: "Supervisor de Lavado - Pedro",
        roles: ["supervisor_lavado"]
    },
    {
        id: 11,
        nombre: "Encargado de Envío - Luis",
        roles: ["envio_planta"]
    }
];

const permisosPorRol = {
    ingeniero: [
        "preparar_soluciones_madre",
        "crear_rol_limpieza",
        "verificar_propagulos_etapa2",
        "verificar_tuppers",
        "checar_reporte_produccion",
        "crear_relacion_material_lavado"
    ],
    medico: [
        "crear_relacion_lavado"
    ],
    operario_medios: [
        "chequeo_osmosis",
        "esterilizacion_autoclave",
        "preparar_medios_cultivo",
        "homogeneizacion_medio",
        "aforacion_medio",
        "llenado_tuppers",
        "etiquetado_tuppers"
    ],
    reporte_siembra: [
        "reporte_siembra_diario"
    ],
    incubadora: [
        "registro_temperatura_humedad",
        "revision_material_operadoras",
        "surtir_material",
        "supervisar_material_adecuado",
        "inventario_etapa3"
    ],
    limpieza_incubadora: [
        "organizar_material_lavado",
        "limpieza_repisas_vacias"
    ],
    operadora_cultivo: [
        "diseccion_propagulos",
        "clasificacion_propagulos",
        "registro_trabajo",
        "etiquetado_tuppers",
        "ingreso_tuppers_incubadora"
    ],
    operadora_lavado: [
        "verificacion_planta_lavado",
        "lavado_plantas",
        "etiquetado_paneras"
    ],
    supervisor_lavado: [
        "supervisar_material_lavado",
        "verificar_identificacion_paneras",
        "reportar_plantas_lavadas",
        "relacion_lavado_completa"
    ],
    envio_planta: [
        "recibir_planta_cajas",
        "transportar_planta_invernadero",
        "entregar_planta_relacion"
    ]
};
