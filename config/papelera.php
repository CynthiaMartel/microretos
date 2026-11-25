<?php

return [
    /*
     * Días que un elemento puede permanecer en la papelera antes de ser
     * eliminado permanentemente por el job automático.
     * Configurable vía PAPELERA_DIAS_RETENCION en el .env.
     */
    'dias_retencion' => (int) env('PAPELERA_DIAS_RETENCION', 30),

    /*
     * Retos a medida: días en la recámara a partir de los cuales Central avisa
     * («lleva N días en la recámara»). Solo es un aviso: nunca se borran solos.
     */
    'recamara_dias_aviso' => (int) env('RECAMARA_DIAS_AVISO', 60),
];
