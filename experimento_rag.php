<?php
/**
 * ══════════════════════════════════════════════════════════════════════════════
 *  EXPERIMENTO: Efecto del conocimiento negativo en LLMs — RAG con Gemini
 *  «La documentación del fracaso» · Blázquez-Ochando et al. (2026)
 *  Rev. Española de Documentación Científica, Vol. 49, N.º 2
 * ══════════════════════════════════════════════════════════════════════════════
 *  AUTORES:
 *   · Manuel Blázquez-Ochando   — manublaz@ucm.es — ORCID 0000-0002-4108-7531
 *   · María Antonia Ovalle Perandones — maovalle@ucm.es — ORCID 0000-0002-3449-7540
 *   · Juan José Prieto Gutiérrez — jjpg@ucm.es  — ORCID 0000-0002-1730-8621
 *   Dpto. Biblioteconomía y Documentación · UCM · Facultad CC. Documentación
 * ══════════════════════════════════════════════════════════════════════════════
 *  REQUISITOS : PHP 7.4+, extensión cURL.
 *  INSTALACIÓN: sube este archivo + promptEval.txt al mismo directorio.
 *  API KEY    : edita GEMINI_API_KEY debajo.
 * ══════════════════════════════════════════════════════════════════════════════
 */

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║  CONFIGURACIÓN EDITABLE                                                  ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ► Clave API — obtén la tuya gratis en https://aistudio.google.com/app/apikey
define('GEMINI_API_KEY',  'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

// ► Modelo (tier gratuito, mayo 2026):
//   gemini-2.5-flash-lite  → 15 RPM / 1 000 RPD  ✓ RECOMENDADO
//   gemini-2.5-flash       → 10 RPM / ~250 RPD
//   gemini-2.5-pro         →  5 RPM / 100 RPD
define('GEMINI_MODEL',    'gemini-2.5-flash-lite');
define('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');
define('MAX_TOKENS',       1024);
define('TEMPERATURE',      0.2);

// ► Ruta al archivo con el prompt de evaluación (mismo directorio)
define('PROMPT_EVAL_FILE', __DIR__ . '/promptEval.txt');

// ── PROMPT DE EVALUACIÓN POR DEFECTO (si no existe promptEval.txt) ─────────
define('PROMPT_EVAL_DEFAULT', <<<'EOT'
Eres un evaluador científico experto. Compara las dos respuestas de IA a la misma consulta.
Respuesta A: generada SIN contexto adicional (solo preentrenamiento).
Respuesta B: generada CON corpus de evidencia negativa curada (RAG negativo).

Devuelve ÚNICAMENTE un objeto JSON válido sin texto adicional:
{
  "puntuacion_A": <0-10>,
  "puntuacion_B": <0-10>,
  "ganador": <"A","B" o "EMPATE">,
  "diferencia": <0-10>,
  "criterios": {
    "precision_factual_A": <0-10>, "precision_factual_B": <0-10>,
    "calibracion_incertidumbre_A": <0-10>, "calibracion_incertidumbre_B": <0-10>,
    "mencion_evidencia_negativa_A": <0-10>, "mencion_evidencia_negativa_B": <0-10>,
    "exhaustividad_A": <0-10>, "exhaustividad_B": <0-10>
  },
  "justificacion": "<máx. 3 oraciones en español>",
  "alerta_alucinacion_A": <true|false>,
  "alerta_alucinacion_B": <true|false>
}
EOT);

// ── EJEMPLOS PRECARGADOS ───────────────────────────────────────────────────
$EJEMPLOS = [
  [
    'id'    => 'bexaroteno',
    'label' => 'Bexaroteno y Alzheimer',
    'query' => '¿Es el bexaroteno efectivo para reducir las placas amiloides en modelos murinos de Alzheimer?',
    'rag'   => "Price et al. (2013) — Journal of Neuroscience:\nWe tested bexarotene in four independent APP transgenic mouse lines and found no significant reduction in amyloid plaque burden. The drug produced clear hepatotoxicity but failed to replicate the soluble Aβ reductions reported by Cramer et al. (2012).\n\nTesseur et al. (2013) — Journal of Neuroscience:\nBexarotene treatment did not reduce amyloid plaque load in our APP/PS1 model. Insoluble Aβ and plaque pathology were unaffected.\n\nVeeraraghavalu et al. (2013) — Science:\nThree independent replication attempts failed to demonstrate any reduction in amyloid burden following bexarotene treatment.\n\nCummings et al. (2016) — Alzheimer's & Dementia (BEAT-AD Trial):\nNo significant effect on amyloid PET signal, cognitive performance, or any secondary endpoint.",
  ],
  [
    'id'    => 'vitamina_e',
    'label' => 'Vitamina E y Alzheimer',
    'query' => '¿Son eficaces los suplementos de vitamina E para prevenir o retrasar el deterioro cognitivo en el Alzheimer?',
    'rag'   => "Petersen et al. (2005) — NEJM:\nVitamin E at 2000 IU/day did not slow progression from mild cognitive impairment to Alzheimer's disease (HR 1.02; 95% CI 0.74–1.41).\n\nKiss et al. (2010) — Cochrane Review:\nNo convincing evidence that vitamin E prevents or treats Alzheimer's disease. High-dose supplementation associated with increased all-cause mortality.\n\nMiller et al. (2005) — Annals of Internal Medicine:\nHigh-dosage vitamin E (≥400 IU/day) may increase all-cause mortality (meta-analysis, n=135,967).",
  ],
  [
    'id'    => 'ivermectina',
    'label' => 'Ivermectina y COVID-19',
    'query' => '¿Tiene la ivermectina eficacia terapéutica demostrada contra la COVID-19?',
    'rag'   => "TOGETHER Trial — Reis et al. (2022) — NEJM:\nIvermectin did not reduce incidence of medical admission (RR 1.03; 95% CI 0.87–1.22; n=1,358).\n\nACTIV-6 — Naggie et al. (2022) — JAMA:\nIvermectin 400 μg/kg did not improve time to recovery vs placebo.\n\nPRINCIPLE Trial — Butler et al. (2023) — Lancet:\nNo improvement in time to recovery (aHR 1.04; 95% CI 0.91–1.18). Stopped for futility.\n\nElenany et al. (2023) — Systematic review (47 trials, n=16,439):\nHigh-quality trials show no clinically meaningful benefit. Early positive signals from small, flawed studies.",
  ],
  [
    'id'    => 'celulas_madre',
    'label' => 'Células madre y lesión medular',
    'query' => '¿Cuál es la evidencia actual sobre el uso de células madre para la recuperación funcional en lesiones medulares?',
    'rag'   => "Tetzlaff et al. (2011) — Journal of Neurotrauma:\nMost early-phase human trials report no significant motor or sensory recovery above placebo levels.\n\nDias et al. (2018) — Stem Cells Translational Medicine:\n29 phase I/II trials: functional recovery outcomes inconsistent and generally modest. Publication bias identified as major confounder.\n\nWang et al. (2021) — Neuroscience Letters (18 RCTs):\nPooled SMD 0.38 (95% CI –0.12 to 0.88). Current evidence does not support routine clinical application.",
  ],
  [
    'id'    => 'homeopatia',
    'label' => 'Homeopatía: evidencia clínica',
    'query' => '¿Existe evidencia científica de que la homeopatía sea más eficaz que el placebo para alguna condición clínica?',
    'rag'   => "Shang et al. (2005) — Lancet (110 homeopathy vs 110 conventional trials):\nOR for homeopathy compatible with chance (OR 0.88; 95% CI 0.65–1.19). Clinical effects are placebo effects.\n\nAustralian NHMRC (2015) — Systematic review of 225 studies:\nNo good-quality evidence for effectiveness across all 68 health conditions examined.\n\nGlasziou et al. (2017) — BMJ Evidence-Based Medicine:\nWhen unpublished trials are modeled, estimated effects approach zero across all indications.",
  ],
];

// ── LEER PROMPT DE EVALUACIÓN ──────────────────────────────────────────────
function leer_prompt_eval(): string {
    if (file_exists(PROMPT_EVAL_FILE)) {
        $txt = trim(file_get_contents(PROMPT_EVAL_FILE));
        if ($txt !== '') return $txt;
    }
    return PROMPT_EVAL_DEFAULT;
}

// ══════════════════════════════════════════════════════════════════════════════
//  PROCESAMIENTO AJAX
// ══════════════════════════════════════════════════════════════════════════════
header('X-Powered-By: NRR-Experiment/3.0');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $api_key = GEMINI_API_KEY;

    // ── run_experiment ─────────────────────────────────────────────────────
    if ($_POST['action'] === 'run_experiment') {
        $pregunta = trim($_POST['pregunta'] ?? '');
        $rag      = trim($_POST['rag_texto'] ?? '');
        if (empty($pregunta)) { echo json_encode(['ok'=>false,'error'=>'Consulta vacía.']); exit; }
        if ($api_key === 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX' || empty($api_key)) {
            echo json_encode(['ok'=>false,'error'=>'API Key no configurada en el archivo PHP.']); exit;
        }
        $prompt_a = "Eres un asistente de investigación científica riguroso. Responde con precisión y objetividad a la siguiente consulta.\n\nConsulta: {$pregunta}\n\nResponde en español, en 3-5 oraciones, con el nivel de detalle propio de un artículo de revisión científica.";
        $prompt_b = "Eres un asistente de investigación científica con acceso a un repositorio de resultados negativos curados (NRR). Integra la evidencia positiva de tu conocimiento general y los resultados negativos del contexto. Cuando existan fracasos de replicación o resultados nulos, adviértelos explícitamente.\n\n--- CORPUS EVIDENCIA NEGATIVA (NRR) ---\n{$rag}\n--- FIN CORPUS ---\n\nConsulta: {$pregunta}\n\nResponde en español, en 4-6 oraciones, sintetizando toda la evidencia (positiva Y negativa) con rigor científico.";
        $t0 = microtime(true); $resp_a = llamar_gemini_api($prompt_a, $api_key); $ms_a = round((microtime(true)-$t0)*1000);
        if (!empty($rag)) {
            $t1 = microtime(true); $resp_b = llamar_gemini_api($prompt_b, $api_key); $ms_b = round((microtime(true)-$t1)*1000);
        } else { $resp_b = ['texto'=>null]; $ms_b = 0; }
        if (isset($resp_a['error'])) { echo json_encode(['ok'=>false,'error'=>$resp_a['error']]); exit; }
        $analisis = analizar_respuestas($resp_a['texto'], $resp_b['texto'] ?? null, $pregunta, $rag);
        echo json_encode([
            'ok'=>true, 'query'=>$pregunta,
            'rag_fuentes'=>contar_fuentes($rag),
            'resp_a'=>$resp_a['texto'], 'resp_b'=>$resp_b['texto'],
            'ms_a'=>$ms_a, 'ms_b'=>$ms_b,
            'analisis'=>$analisis,
            'timestamp'=>date('Y-m-d H:i:s'),
            'modelo'=>GEMINI_MODEL, 'temperatura'=>TEMPERATURE,
        ]); exit;
    }

    // ── evaluate ───────────────────────────────────────────────────────────
    if ($_POST['action'] === 'evaluate') {
        $resp_a   = trim($_POST['resp_a'] ?? '');
        $resp_b   = trim($_POST['resp_b'] ?? '');
        $pregunta = trim($_POST['pregunta'] ?? '');
        if (empty($resp_a) || empty($resp_b)) { echo json_encode(['ok'=>false,'error'=>'Faltan respuestas para evaluar.']); exit; }
        if ($api_key === 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX' || empty($api_key)) {
            echo json_encode(['ok'=>false,'error'=>'API Key no configurada.']); exit;
        }
        $prompt_eval = leer_prompt_eval();
        $prompt_completo = $prompt_eval . "\n\n---\nCONSULTA ORIGINAL: {$pregunta}\n\nRESPUESTA A (sin RAG):\n{$resp_a}\n\nRESPUESTA B (con RAG negativo):\n{$resp_b}";
        $resultado_eval = llamar_gemini_api($prompt_completo, $api_key, 512);
        if (isset($resultado_eval['error'])) { echo json_encode(['ok'=>false,'error'=>$resultado_eval['error']]); exit; }
        // Limpiar y parsear JSON de la respuesta
        $raw = $resultado_eval['texto'];
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);
        $parsed = json_decode(trim($raw), true);
        if (!$parsed) { echo json_encode(['ok'=>false,'error'=>'Respuesta del evaluador no es JSON válido: '.htmlspecialchars(substr($raw,0,200))]); exit; }
        echo json_encode(['ok'=>true, 'evaluacion'=>$parsed]); exit;
    }

    // ── save_prompt_eval ──────────────────────────────────────────────────
    if ($_POST['action'] === 'save_prompt_eval') {
        $nuevo = trim($_POST['prompt_eval'] ?? '');
        if (empty($nuevo)) { echo json_encode(['ok'=>false,'error'=>'El prompt no puede estar vacío.']); exit; }
        $ok = file_put_contents(PROMPT_EVAL_FILE, $nuevo);
        echo json_encode(['ok' => $ok !== false]); exit;
    }

    // ── get_prompt_eval ──────────────────────────────────────────────────
    if ($_POST['action'] === 'get_prompt_eval') {
        echo json_encode(['ok'=>true,'prompt'=>leer_prompt_eval()]); exit;
    }

    // ── parse_txt ─────────────────────────────────────────────────────────
    if ($_POST['action'] === 'parse_txt') {
        echo json_encode(parsear_txt(trim($_POST['contenido'] ?? ''))); exit;
    }

    echo json_encode(['ok'=>false,'error'=>'Acción no reconocida.']); exit;
}

// ══════════════════════════════════════════════════════════════════════════════
//  FUNCIONES PHP
// ══════════════════════════════════════════════════════════════════════════════
function llamar_gemini_api(string $prompt, string $api_key, int $max_tokens = 0): array {
    $url  = GEMINI_ENDPOINT . '?key=' . urlencode($api_key);
    $body = json_encode([
        'contents' => [['role'=>'user','parts'=>[['text'=>$prompt]]]],
        'generationConfig' => ['temperature'=>TEMPERATURE, 'maxOutputTokens'=> $max_tokens ?: MAX_TOKENS],
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_TIMEOUT=>45, CURLOPT_SSL_VERIFYPEER=>true]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch,CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
    if ($cerr) return ['error'=>'Error cURL: '.$cerr];
    if ($code !== 200) { $d=json_decode($resp,true); return ['error'=>'Gemini API '.$code.': '.($d['error']['message']??$resp)]; }
    $d = json_decode($resp, true);
    $text = $d['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($text)) return ['error'=>'Respuesta vacía. Verifica la API key.'];
    return ['texto'=>trim($text)];
}

function parsear_txt(string $contenido): array {
    $bloques = preg_split('/\n[-=]{3,}\n/', $contenido);
    $res = [];
    foreach ($bloques as $b) {
        $b = trim($b); if (empty($b)) continue;
        $q=''; $r='';
        if (preg_match('/^QUERY:\s*(.+?)(?:\n|$)/im',$b,$m)) $q=trim($m[1]);
        if (preg_match('/^RAG:\s*\n([\s\S]+)/im',$b,$m)) $r=trim($m[1]);
        if ($q) $res[]=['query'=>$q,'rag'=>$r,'raw'=>$b];
    }
    return ['ok'=>!empty($res),'bloques'=>$res,'total'=>count($res)];
}

function contar_fuentes(string $rag): int {
    if (empty($rag)) return 0;
    $c=0;
    foreach (explode("\n",$rag) as $l)
        if (preg_match('/^\s*[A-Z][a-zA-Z\s]+et al\.\s*\(|\s*—\s*[A-Z]/',$l)) $c++;
    return max(1,$c);
}

function analizar_respuestas(?string $ra, ?string $rb, string $q, string $rag): array {
    if (!$ra) return [];
    $kw_neg=['no se encontró','no encontró','no logró','no replicó','fracasó','fallida',
             'sin efecto significativo','no demostró','failed','no significant','nulo',
             'no eficacia','no apoya','no respalda','evidencia insuficiente','no confirmó',
             'not significant','no difference','inconcluso','negativo','no encontraron',
             'no mejora','no reduce','no tiene eficacia'];
    $kw_pos=['eficaz','eficacia','demostró','mostró','reduce','mejora','beneficio',
             'effective','significant','prometedor','positivo','confirmó','ha demostrado'];
    $ta=mb_strtolower($ra); $tb=$rb?mb_strtolower($rb):'';
    $na=0; foreach($kw_neg as $k) if(str_contains($ta,$k)) $na++;
    $pa=0; foreach($kw_pos as $k) if(str_contains($ta,$k)) $pa++;
    $nb=0; foreach($kw_neg as $k) if(str_contains($tb,$k)) $nb++;
    $pb=0; foreach($kw_pos as $k) if(str_contains($tb,$k)) $pb++;
    $sa=($pa>$na)?'POSITIVO':(($na>$pa)?'NEGATIVO/CAUTELOSO':'NEUTRO');
    $sb=$rb?(($pb>$nb)?'POSITIVO':(($nb>$pb)?'NEGATIVO/CAUTELOSO':'NEUTRO')):'N/A';
    $wa=str_word_count($ra); $wb=$rb?str_word_count($rb):0;
    $rag_ok=false;
    if($rb && !empty($rag)){
        preg_match_all('/^([A-Z][a-z]+)\s+et al\./m',$rag,$mr);
        foreach(($mr[1]??[]) as $ap) if(str_contains(mb_strtolower($rb),mb_strtolower($ap))){ $rag_ok=true; break; }
        foreach($kw_neg as $k) if(str_contains($tb,$k)){ $rag_ok=true; break; }
    }
    $mejora=false; $mdesc='No evaluable (sin respuesta B o sin RAG)';
    if($rb && !empty($rag)){
        // Criterio ampliado: también diferencia de palabras y citas
        $diff_palabras = $wb - $wa;
        if($nb>$na || $sb==='NEGATIVO/CAUTELOSO'){
            $mejora=true; $mdesc='Sí — B incluye más señales cautelosas/negativas que A';
        } elseif($sa==='POSITIVO' && $sb!=='POSITIVO'){
            $mejora=true; $mdesc='Sí — B corrige el sesgo positivo de A';
        } elseif($rag_ok && $diff_palabras > 20){
            $mejora=true; $mdesc='Sí — B aprovecha el RAG y es significativamente más exhaustiva ('.$diff_palabras.' palabras extra)';
        } else {
            $mdesc='Indeterminado — las métricas de sesgo son similares; se recomienda evaluación por IA (botón «Evaluar con IA»)';
        }
    }
    return [
        'sesgo_a'=>$sa,'sesgo_b'=>$sb,
        'keywords_neg_a'=>$na,'keywords_pos_a'=>$pa,
        'keywords_neg_b'=>$nb,'keywords_pos_b'=>$pb,
        'palabras_a'=>$wa,'palabras_b'=>$wb,
        'rag_aprovechado'=>$rag_ok,
        'mejora_calibracion'=>$mejora,'mejora_desc'=>$mdesc,
        'tiene_advertencia_b'=>($nb>=2),
    ];
}

// ── Preparar datos para JS ─────────────────────────────────────────────────
$EJEMPLOS_JSON = json_encode($EJEMPLOS, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP);
$PROMPT_EVAL_INICIAL = htmlspecialchars(leer_prompt_eval(), ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Experimento RAG — Documentación del fracaso · UCM 2026</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,300;0,400;0,600;0,700;1,400&family=Source+Code+Pro:wght@400;600&family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════════════════
   DESIGN TOKENS — LIGHT THEME (WCAG AA compliant)
   ══════════════════════════════════════════════════════════ */
:root {
  /* Backgrounds */
  --bg:        #f5f6f8;
  --surface:   #ffffff;
  --surface2:  #f0f2f5;
  --surface3:  #e8ebf0;

  /* Borders */
  --border:    #d0d5dd;
  --border-md: #b0b8c8;

  /* Text — all pass WCAG AA (≥4.5:1 on white) */
  --text:      #111827;   /* 16.75:1 */
  --text-md:   #374151;   /* 10.0:1  */
  --text-dim:  #6b7280;   /*  5.9:1  */
  --text-faint:#9ca3af;   /* use only for decorative / large text */

  /* Accent A — warm orange, WCAG AA on white */
  --A:         #c05621;   /* 4.6:1 */
  --A-bg:      #fff3ec;
  --A-border:  #f6ad8e;

  /* Accent B — blue, WCAG AA on white */
  --B:         #1d6fa4;   /* 5.3:1 */
  --B-bg:      #eff6ff;
  --B-border:  #93c5fd;

  /* Semantic */
  --green:     #15803d;   /* 5.1:1 */
  --green-bg:  #f0fdf4;
  --green-bd:  #86efac;

  --yellow:    #92400e;   /* 6.4:1 — using brown for text on light bg */
  --yellow-bg: #fffbeb;
  --yellow-bd: #fcd34d;

  --red:       #b91c1c;   /* 7.2:1 */
  --red-bg:    #fef2f2;
  --red-bd:    #fca5a5;

  --purple:    #6d28d9;   /* 7.7:1 */
  --purple-bg: #f5f3ff;
  --purple-bd: #c4b5fd;

  --eval:      #0e7490;   /* teal for evaluator — 5.0:1 */
  --eval-bg:   #ecfeff;
  --eval-bd:   #67e8f9;

  /* Misc */
  --r:         8px;
  --shadow:    0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
  --shadow-md: 0 4px 12px rgba(0,0,0,.08);
}

/* ── RESET ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:15px;scroll-behavior:smooth;overflow-x:hidden}
body{background:var(--bg);color:var(--text);font-family:'Source Sans 3',system-ui,sans-serif;line-height:1.6;min-height:100vh;overflow-x:hidden;min-width:0;width:100%}

/* ── TOPBAR ── */
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:.65rem 1.6rem;display:flex;align-items:center;justify-content:space-between;gap:.8rem;flex-wrap:wrap;position:sticky;top:0;z-index:200;box-shadow:var(--shadow)}
.topbar-left{display:flex;align-items:center;gap:.8rem}
.chip{background:linear-gradient(135deg,var(--A),var(--B));color:#fff;font-weight:700;font-size:.66rem;padding:.22rem .55rem;border-radius:4px;letter-spacing:.07em;text-transform:uppercase;white-space:nowrap}
.app-name{font-family:'Libre Baskerville',serif;font-size:.97rem;color:var(--text);line-height:1.25}
.app-name span{display:block;font-family:'Source Sans 3',sans-serif;font-size:.7rem;color:var(--text-dim);font-weight:400}
.topbar-authors{font-size:.68rem;color:var(--text-faint);text-align:right;line-height:1.55}
.topbar-authors a{color:var(--B);text-decoration:none}
.topbar-authors a:hover,.topbar-authors a:focus{text-decoration:underline;outline:2px solid var(--B);outline-offset:1px;border-radius:2px}

/* ── LAYOUT ── */
.wrap{max-width:1300px;margin:0 auto;padding:1.4rem 1.6rem;min-width:0;overflow:hidden}
.grid-main{display:grid;grid-template-columns:370px minmax(0,1fr);gap:1.4rem;align-items:start;min-width:0}
@media(max-width:920px){.grid-main{grid-template-columns:1fr}}

/* ── TOPBAR ── */
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:.65rem 1.6rem;display:flex;align-items:center;justify-content:space-between;gap:.8rem;flex-wrap:wrap;position:sticky;top:0;z-index:200;box-shadow:var(--shadow);min-width:0;width:100%}
.topbar-left{display:flex;align-items:center;gap:.8rem;min-width:0;flex-shrink:1}
.app-name{font-family:'Libre Baskerville',serif;font-size:.97rem;color:var(--text);line-height:1.25;min-width:0}
.app-name span{display:block;font-family:'Source Sans 3',sans-serif;font-size:.7rem;color:var(--text-dim);font-weight:400}
.topbar-authors{font-size:.68rem;color:var(--text-faint);text-align:right;line-height:1.55;flex-shrink:1;min-width:0}
.topbar-authors a{color:var(--B);text-decoration:none}
.topbar-authors a:hover,.topbar-authors a:focus{text-decoration:underline;outline:2px solid var(--B);outline-offset:1px;border-radius:2px}
.tabs-bar{display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:1.1rem}
.tab-btn{background:none;border:none;padding:.55rem 1.1rem;font-size:.8rem;font-weight:600;color:var(--text-dim);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s;border-radius:4px 4px 0 0;white-space:nowrap}
.tab-btn:hover{color:var(--text-md)}
.tab-btn:focus-visible{outline:2px solid var(--B);outline-offset:2px}
.tab-btn.active{color:var(--B);border-bottom-color:var(--B);background:var(--B-bg)}
.tab-panel{display:none}
.tab-panel.active{display:block}

/* ── CARD ── */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;box-shadow:var(--shadow);margin-bottom:1rem}
.card-head{padding:.65rem 1rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.55rem;font-size:.7rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--text-dim);background:var(--surface2)}
.card-body{padding:1rem}
.card-head .dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}

/* ── FORM ELEMENTS ── */
.field{margin-bottom:.85rem}
.field>label{display:block;font-size:.71rem;font-weight:700;color:var(--text-md);letter-spacing:.06em;text-transform:uppercase;margin-bottom:.3rem}
.field>label small{text-transform:none;font-weight:400;color:var(--text-faint);margin-left:.3rem}
input[type=text],textarea,select{
  width:100%;background:var(--surface);border:1px solid var(--border-md);border-radius:5px;
  color:var(--text);padding:.52rem .72rem;font-family:inherit;font-size:.9rem;
  resize:vertical;transition:border-color .2s,box-shadow .2s;
}
input[type=text]:focus,textarea:focus,select:focus{
  outline:none;border-color:var(--B);box-shadow:0 0 0 3px rgba(29,111,164,.15)
}
textarea{min-height:100px;line-height:1.55}
.apikey-note{font-size:.69rem;color:var(--text-dim);margin-top:.3rem;line-height:1.45}
.apikey-note a{color:var(--B);text-decoration:none}
.apikey-note a:hover{text-decoration:underline}
.api-status{display:inline-flex;align-items:center;gap:.3rem;font-size:.7rem;padding:.2rem .55rem;border-radius:100px;margin-top:.3rem;font-weight:600}
.api-status.ok{background:var(--green-bg);color:var(--green);border:1px solid var(--green-bd)}
.api-status.warn{background:var(--yellow-bg);color:var(--yellow);border:1px solid var(--yellow-bd)}
code.inline{font-family:'Source Code Pro',monospace;background:var(--surface2);padding:.1rem .3rem;border-radius:3px;color:var(--purple);font-size:.72rem;border:1px solid var(--purple-bd)}

/* EXAMPLE PILLS */
.ex-grid{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.35rem}
.ex-pill{background:var(--surface2);border:1px solid var(--border);border-radius:5px;padding:.28rem .6rem;font-size:.72rem;color:var(--text-md);cursor:pointer;transition:border-color .15s,color .15s,background .15s;line-height:1.3;display:flex;align-items:center;gap:.35rem}
.ex-pill:hover,.ex-pill:focus-visible{border-color:var(--B);color:var(--B);background:var(--B-bg);outline:none}
.ex-pill.selected{border-color:var(--B);color:var(--B);background:var(--B-bg);font-weight:600}

/* TOGGLE */
.toggle-wrap{display:flex;align-items:center;gap:.65rem;background:var(--surface2);border:1px solid var(--border);border-radius:5px;padding:.55rem .75rem;cursor:pointer;margin-bottom:.85rem}
.sw{position:relative;width:36px;height:20px;flex-shrink:0}
.sw input{opacity:0;width:0;height:0;position:absolute}
.sw-track{position:absolute;inset:0;background:var(--surface3);border-radius:20px;border:1px solid var(--border-md);transition:background .2s,border-color .2s}
.sw-track::after{content:'';position:absolute;left:3px;top:3px;width:12px;height:12px;background:var(--text-faint);border-radius:50%;transition:transform .2s,background .2s}
.sw input:checked+.sw-track{background:var(--B);border-color:var(--B)}
.sw input:checked+.sw-track::after{transform:translateX(16px);background:#fff}
.sw input:focus-visible+.sw-track{outline:2px solid var(--B);outline-offset:2px}
.toggle-label{font-size:.82rem;color:var(--text-md);user-select:none;line-height:1.3}
.toggle-label strong{color:var(--B)}

/* DRAG & DROP */
.drop-zone{border:2px dashed var(--border-md);border-radius:var(--r);padding:1.2rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;position:relative;background:var(--surface)}
.drop-zone:hover,.drop-zone:focus-within,.drop-zone.drag-over{border-color:var(--B);background:var(--B-bg)}
.drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.drop-icon{font-size:1.6rem;margin-bottom:.4rem;display:block}
.drop-text{font-size:.8rem;color:var(--text-dim);line-height:1.5}
.drop-text strong{color:var(--text-md)}
.file-queue{margin-top:.5rem;display:flex;flex-direction:column;gap:.3rem}
.fq-item{background:var(--surface2);border:1px solid var(--border);border-radius:4px;padding:.3rem .65rem;font-size:.74rem;color:var(--text-dim);display:flex;align-items:center;justify-content:space-between;gap:.4rem}
.fq-name{color:var(--text-md);font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px}
.fq-status{font-size:.66rem;padding:.1rem .4rem;border-radius:100px}
.fq-load{background:var(--B-bg);color:var(--B);border:1px solid var(--B-border)}
.fq-ok  {background:var(--green-bg);color:var(--green);border:1px solid var(--green-bd)}
.fq-err {background:var(--red-bg);color:var(--red);border:1px solid var(--red-bd)}
.fq-use{background:none;border:1px solid var(--border);border-radius:3px;color:var(--B);font-size:.68rem;padding:.1rem .4rem;cursor:pointer;white-space:nowrap;transition:background .15s}
.fq-use:hover{background:var(--B-bg)}
.format-note{background:var(--surface2);border:1px solid var(--border);border-radius:5px;padding:.65rem .85rem;font-size:.72rem;color:var(--text-dim);margin-top:.5rem;line-height:1.65}

/* BUTTONS */
.btn-run{width:100%;background:linear-gradient(135deg,#c05621,#9c4320);color:#fff;border:none;border-radius:6px;padding:.7rem;font-size:.92rem;font-weight:700;cursor:pointer;letter-spacing:.03em;transition:opacity .15s,transform .1s,box-shadow .15s;display:flex;align-items:center;justify-content:center;gap:.45rem;box-shadow:0 2px 4px rgba(192,86,33,.25)}
.btn-run:hover{opacity:.92;box-shadow:0 4px 8px rgba(192,86,33,.3)}
.btn-run:focus-visible{outline:2px solid var(--A);outline-offset:2px}
.btn-run:active{transform:scale(.98)}
.btn-run:disabled{opacity:.45;cursor:not-allowed;box-shadow:none}
.btn-eval{width:100%;background:linear-gradient(135deg,#0e7490,#0a5a70);color:#fff;border:none;border-radius:6px;padding:.6rem;font-size:.88rem;font-weight:700;cursor:pointer;letter-spacing:.03em;transition:opacity .15s,transform .1s;display:flex;align-items:center;justify-content:center;gap:.4rem;margin-top:.5rem}
.btn-eval:hover{opacity:.9}
.btn-eval:focus-visible{outline:2px solid var(--eval);outline-offset:2px}
.btn-eval:active{transform:scale(.98)}
.btn-eval:disabled{opacity:.4;cursor:not-allowed}
.btn-secondary{background:var(--surface2);border:1px solid var(--border-md);border-radius:5px;color:var(--text-md);font-size:.78rem;font-weight:600;padding:.35rem .75rem;cursor:pointer;transition:background .15s,border-color .15s}
.btn-secondary:hover{background:var(--surface3);border-color:var(--border-md)}
.btn-secondary:focus-visible{outline:2px solid var(--B);outline-offset:2px}
.spinner{width:14px;height:14px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:none}
@keyframes spin{to{transform:rotate(360deg)}}

/* PROMPT EDITOR */
.prompt-editor-wrap textarea{font-family:'Source Code Pro',monospace;font-size:.78rem;min-height:280px;line-height:1.65;background:var(--surface);color:var(--text);border:1px solid var(--border-md)}
.prompt-editor-wrap textarea:focus{border-color:var(--eval);box-shadow:0 0 0 3px rgba(14,116,144,.12)}
.prompt-save-row{display:flex;align-items:center;gap:.6rem;margin-top:.5rem;flex-wrap:wrap}
.prompt-save-msg{font-size:.74rem;color:var(--green);font-weight:600;display:none}
.prompt-save-msg.err{color:var(--red)}

/* ── RESULTS AREA ── */
#results-area{display:none;min-width:0;overflow:hidden}
.results-meta{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:.75rem 1.1rem;margin-bottom:1rem;display:flex;align-items:center;flex-wrap:wrap;gap:.65rem;box-shadow:var(--shadow);min-width:0}
.query-display{font-size:.87rem;font-style:italic;color:var(--text-md);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.meta-tags{display:flex;gap:.35rem;flex-wrap:wrap}
.mtag{font-size:.67rem;padding:.16rem .48rem;border-radius:100px;font-weight:600;border:1px solid}
.mtag-model{background:var(--purple-bg);color:var(--purple);border-color:var(--purple-bd)}
.mtag-ts{background:var(--surface2);color:var(--text-dim);border-color:var(--border)}
.mtag-src{background:var(--B-bg);color:var(--B);border-color:var(--B-border)}
.mtag-eval{background:var(--eval-bg);color:var(--eval);border-color:var(--eval-bd)}

.resp-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;min-width:0}
@media(max-width:700px){.resp-grid{grid-template-columns:1fr}}

.resp-card{background:var(--surface);border-radius:var(--r);overflow:hidden;display:flex;flex-direction:column;border:1px solid var(--border);box-shadow:var(--shadow)}
.resp-head{padding:.6rem .9rem;display:flex;align-items:center;justify-content:space-between}
.resp-head.A{background:var(--A-bg);border-bottom:2px solid var(--A)}
.resp-head.B{background:var(--B-bg);border-bottom:2px solid var(--B)}
.resp-head-label{font-size:.69rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase}
.A .resp-head-label{color:var(--A)}
.B .resp-head-label{color:var(--B)}
.resp-badge{font-size:.64rem;padding:.13rem .42rem;border-radius:100px;font-weight:600;border:1px solid}
.A .resp-badge{background:var(--A-bg);color:var(--A);border-color:var(--A-border)}
.B .resp-badge{background:var(--B-bg);color:var(--B);border-color:var(--B-border)}
.resp-body{padding:.9rem;flex:1}
.resp-text{font-size:.89rem;line-height:1.75;color:var(--text-md);white-space:pre-wrap}
.resp-foot{padding:.4rem .9rem;border-top:1px solid var(--border);font-size:.67rem;color:var(--text-faint);display:flex;align-items:center;gap:.35rem;background:var(--surface2)}

/* ── ANÁLISIS LÉXICO ── */
.analysis-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);margin-bottom:1rem;overflow:hidden;box-shadow:var(--shadow)}
.analysis-head{padding:.65rem 1rem;border-bottom:1px solid var(--border);font-size:.7rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--yellow);display:flex;align-items:center;gap:.45rem;background:var(--yellow-bg)}
.analysis-body{padding:1rem}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.65rem;margin-bottom:.9rem}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:.65rem .85rem}
.stat-label{font-size:.66rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.18rem}
.stat-val{font-size:1.18rem;font-weight:700;color:var(--text);line-height:1.1}
.stat-val.ok{color:var(--green)}
.stat-val.warn{color:var(--yellow)}
.stat-val.bad{color:var(--red)}
.veredicto{border-radius:6px;padding:.85rem 1rem;margin-bottom:.9rem;font-size:.87rem;line-height:1.6;display:flex;align-items:flex-start;gap:.65rem;border:1px solid}
.veredicto.pass{background:var(--green-bg);border-color:var(--green-bd);color:var(--green)}
.veredicto.warn{background:var(--yellow-bg);border-color:var(--yellow-bd);color:var(--yellow)}
.veredicto.neutral{background:var(--surface2);border-color:var(--border);color:var(--text-dim)}
.veredicto-icon{font-size:1.1rem;flex-shrink:0;margin-top:.1rem}
.veredicto-text strong{color:inherit;font-weight:700}
.kw-compare{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}
.kw-compare .A h4{color:var(--A);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.45rem}
.kw-compare .B h4{color:var(--B);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.45rem}
.kw-bar-wrap{display:flex;align-items:center;gap:.45rem;margin-bottom:.3rem;font-size:.77rem}
.kw-bar-label{width:68px;color:var(--text-dim);text-align:right;flex-shrink:0}
.kw-bar-track{flex:1;background:var(--surface3);border-radius:100px;height:7px;overflow:hidden;border:1px solid var(--border)}
.kw-bar-fill{height:100%;border-radius:100px;transition:width .5s}
.kw-bar-fill.pos{background:var(--A)}
.kw-bar-fill.neg{background:var(--B)}
.kw-bar-num{width:18px;color:var(--text-md);font-weight:600;text-align:right}

/* ── EVALUACIÓN IA ── */
.eval-card{background:var(--surface);border:1px solid var(--eval-bd);border-radius:var(--r);margin-bottom:1rem;overflow:hidden;box-shadow:var(--shadow)}
.eval-head{padding:.65rem 1rem;border-bottom:1px solid var(--eval-bd);font-size:.7rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--eval);display:flex;align-items:center;gap:.45rem;background:var(--eval-bg)}
.eval-body{padding:1rem}
.eval-score-row{display:flex;gap:1rem;margin-bottom:.8rem;flex-wrap:wrap}
.eval-score-box{flex:1;min-width:100px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:.65rem .9rem;text-align:center}
.eval-score-box.winner{border-color:var(--eval-bd);background:var(--eval-bg)}
.eval-score-label{font-size:.68rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.15rem}
.eval-score-val{font-size:2rem;font-weight:700;line-height:1;color:var(--text)}
.eval-score-box.winner .eval-score-val{color:var(--eval)}
.eval-winner-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;font-weight:700;padding:.18rem .55rem;border-radius:100px;background:var(--eval-bg);color:var(--eval);border:1px solid var(--eval-bd);margin-top:.3rem}
.eval-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.55rem;margin-bottom:.8rem}
.eval-crit-box{background:var(--surface2);border:1px solid var(--border);border-radius:5px;padding:.55rem .75rem}
.eval-crit-label{font-size:.66rem;color:var(--text-dim);margin-bottom:.2rem}
.eval-crit-bars{display:flex;gap:.3rem;align-items:center}
.eval-crit-bar{flex:1;height:6px;background:var(--surface3);border-radius:100px;overflow:hidden}
.eval-crit-bar-fill{height:100%;border-radius:100px;transition:width .5s}
.eval-crit-bar-fill.fa{background:var(--A)}
.eval-crit-bar-fill.fb{background:var(--B)}
.eval-crit-nums{font-size:.72rem;color:var(--text-md);font-weight:600;white-space:nowrap}
.eval-justif{background:var(--surface2);border-left:3px solid var(--eval);border-radius:0 5px 5px 0;padding:.65rem .9rem;font-size:.85rem;color:var(--text-md);line-height:1.6;margin-top:.6rem}
.eval-alert{display:inline-flex;align-items:center;gap:.3rem;font-size:.7rem;padding:.15rem .5rem;border-radius:100px;font-weight:600;margin-right:.3rem}
.eval-alert.yes{background:var(--red-bg);color:var(--red);border:1px solid var(--red-bd)}
.eval-alert.no {background:var(--green-bg);color:var(--green);border:1px solid var(--green-bd)}
#eval-loading{display:none;align-items:center;gap:.6rem;font-size:.82rem;color:var(--eval);padding:.5rem 0}
#eval-error{display:none;background:var(--red-bg);color:var(--red);border:1px solid var(--red-bd);border-radius:5px;padding:.6rem .8rem;font-size:.82rem;margin-top:.5rem}

/* ── HISTORIAL ── */
.history-card{margin-top: 50px; background:var(--surface);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;margin-bottom:1.2rem;box-shadow:var(--shadow);min-width:0;max-width:100%}
.history-head{padding:.6rem 1rem;border-bottom:1px solid var(--border);font-size:.7rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--text-dim);display:flex;align-items:center;justify-content:space-between;background:var(--surface2)}
.btn-export{background:none;border:1px solid var(--border);border-radius:4px;color:var(--text-dim);font-size:.69rem;padding:.18rem .52rem;cursor:pointer;transition:color .15s,border-color .15s,background .15s}
.btn-export:hover{color:var(--green);border-color:var(--green-bd);background:var(--green-bg)}
.btn-export:focus-visible{outline:2px solid var(--B);outline-offset:2px}
.history-table-wrap{overflow-x:auto;display:none;max-width:100%}
.history-table{width:max-content;min-width:100%;border-collapse:collapse;font-size:.73rem}
.history-table th{padding:.4rem .65rem;text-align:left;font-size:.64rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-dim);border-bottom:1px solid var(--border);background:var(--surface2);white-space:nowrap}
.history-table td{padding:.4rem .65rem;border-bottom:1px solid var(--border);color:var(--text-md);vertical-align:top;line-height:1.4;white-space:nowrap}
.history-table tr:last-child td{border-bottom:none}
.history-table tr:hover td{background:var(--surface2)}
.pill-pass{background:var(--green-bg);color:var(--green);border:1px solid var(--green-bd);border-radius:100px;font-size:.63rem;padding:.1rem .38rem;font-weight:700;white-space:nowrap}
.pill-warn{background:var(--yellow-bg);color:var(--yellow);border:1px solid var(--yellow-bd);border-radius:100px;font-size:.63rem;padding:.1rem .38rem;font-weight:700;white-space:nowrap}
.pill-na{background:var(--surface2);color:var(--text-faint);border:1px solid var(--border);border-radius:100px;font-size:.63rem;padding:.1rem .38rem;white-space:nowrap}
.pill-eval{background:var(--eval-bg);color:var(--eval);border:1px solid var(--eval-bd);border-radius:100px;font-size:.63rem;padding:.1rem .38rem;font-weight:700;white-space:nowrap}
.txt-q{max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text)}
.empty-history{padding:1.1rem;text-align:center;font-size:.8rem;color:var(--text-faint)}

/* ── FOOTER ── */
.footer{background:var(--surface);border-top:1px solid var(--border);padding:.85rem 1.6rem;font-size:.69rem;color:var(--text-dim);display:flex;justify-content:space-between;flex-wrap:wrap;gap:.45rem;margin-top:1.8rem}
.footer a{color:var(--B);text-decoration:none}
.footer a:hover{text-decoration:underline}

/* UTIL */
.section-sep{border:none;border-top:1px solid var(--border);margin:1rem 0}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar" role="banner">
  <div class="topbar-left">
    <span class="chip" aria-hidden="true">NRR · Exp</span>
    <div class="app-name">
      Experimento: efecto del conocimiento negativo en LLMs
      <span>RAG con resultados negativos curados · Google Gemini API</span>
    </div>
  </div>
  <div class="topbar-authors">
    <strong>Blázquez-Ochando, M.</strong>&nbsp;<a href="https://orcid.org/0000-0002-4108-7531" target="_blank" rel="noopener" aria-label="ORCID de Blázquez-Ochando">0000-0002-4108-7531</a>&nbsp;·&nbsp;
    <strong>Ovalle Perandones, M.A.</strong>&nbsp;<a href="https://orcid.org/0000-0002-3449-7540" target="_blank" rel="noopener" aria-label="ORCID de Ovalle Perandones">0000-0002-3449-7540</a>&nbsp;·&nbsp;
    <strong>Prieto Gutiérrez, J.J.</strong>&nbsp;<a href="https://orcid.org/0000-0002-1730-8621" target="_blank" rel="noopener" aria-label="ORCID de Prieto Gutiérrez">0000-0002-1730-8621</a><br>
    Dpto. Biblioteconomía y Documentación · UCM
  </div>
</header>

<div class="wrap">
<div class="grid-main">

<!-- ══ PANEL IZQUIERDO ═════════════════════════════════════════════════════ -->
<div>

  <!-- TABS -->
  <nav class="tabs-bar" role="tablist" aria-label="Secciones del panel de control">
    <button class="tab-btn active" role="tab" aria-selected="true"  aria-controls="tab-exp"    id="tbtn-exp">🔬 Experimento</button>
    <button class="tab-btn"        role="tab" aria-selected="false" aria-controls="tab-files"  id="tbtn-files">📂 Archivos TXT</button>
    <button class="tab-btn"        role="tab" aria-selected="false" aria-controls="tab-config" id="tbtn-config">⚙ Config</button>
    <button class="tab-btn"        role="tab" aria-selected="false" aria-controls="tab-prompt" id="tbtn-prompt">✏ Prompt Eval.</button>
  </nav>

  <!-- TAB: EXPERIMENTO -->
  <div id="tab-exp" role="tabpanel" aria-labelledby="tbtn-exp" class="tab-panel active">

    <div class="card">
      <div class="card-head"><span class="dot" style="background:var(--A)"></span>Consulta científica</div>
      <div class="card-body">

        <div class="field">
          <label>Ejemplos precargados <small>(carga query + RAG)</small></label>
          <div class="ex-grid" id="ex-grid" role="list">
            <?php foreach ($EJEMPLOS as $ej): ?>
            <button type="button" class="ex-pill" role="listitem" data-id="<?= htmlspecialchars($ej['id']) ?>" aria-label="Cargar ejemplo: <?= htmlspecialchars($ej['label']) ?>">
              <span aria-hidden="true">🔬</span><?= htmlspecialchars($ej['label']) ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <hr class="section-sep">

        <div class="field">
          <label for="inp-query">Consulta (query)</label>
          <input type="text" id="inp-query" placeholder="¿Es el bexaroteno efectivo para...?" aria-required="true">
        </div>

        <div class="field">
          <label for="inp-rag">Corpus RAG negativo <small>(evidencia curada)</small></label>
          <textarea id="inp-rag" rows="7" placeholder="Autor et al. (año) — Revista:&#10;Texto del resultado negativo..." aria-label="Corpus de evidencia negativa para RAG"></textarea>
        </div>

        <label class="toggle-wrap" for="tog-rag">
          <span class="sw">
            <input type="checkbox" id="tog-rag" checked aria-label="Activar condición B con RAG negativo">
            <span class="sw-track" aria-hidden="true"></span>
          </span>
          <span class="toggle-label">Ejecutar también <strong>Condición B — RAG negativo</strong></span>
        </label>

        <button class="btn-run" id="btn-run" onclick="ejecutarExperimento()" aria-live="polite">
          <span id="btn-spin" class="spinner" aria-hidden="true"></span>
          <span id="btn-text">▶ Ejecutar experimento</span>
        </button>

      </div>
    </div>

  </div><!-- end tab-exp -->

  <!-- TAB: ARCHIVOS TXT -->
  <div id="tab-files" role="tabpanel" aria-labelledby="tbtn-files" class="tab-panel">
    <div class="card">
      <div class="card-head"><span class="dot" style="background:var(--purple)"></span>Cargar casos desde archivo TXT</div>
      <div class="card-body">
        <div class="drop-zone" id="drop-zone" role="button" aria-label="Zona de arrastre para archivos TXT" tabindex="0">
          <input type="file" id="file-input" accept=".txt" multiple aria-label="Seleccionar archivos TXT">
          <span class="drop-icon" aria-hidden="true">📂</span>
          <div class="drop-text"><strong>Arrastra y suelta</strong> archivos .txt o haz clic<br>Se admiten varios archivos a la vez</div>
        </div>
        <div class="file-queue" id="file-queue" aria-live="polite"></div>
        <div class="format-note">
          <strong>Formato TXT:</strong><br>
          <code class="inline">QUERY: ¿Pregunta científica?</code><br>
          <code class="inline">RAG:</code><br>
          <code class="inline">Autor et al. (año) — Revista:</code><br>
          <code class="inline">Texto del resultado negativo.</code><br><br>
          Separa varios casos con una línea de <code class="inline">---</code>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB: CONFIG -->
  <div id="tab-config" role="tabpanel" aria-labelledby="tbtn-config" class="tab-panel">
    <div class="card">
      <div class="card-head"><span class="dot" style="background:var(--B)"></span>Estado de la API y configuración</div>
      <div class="card-body">
        <div class="field">
          <label>Estado de la API</label>
          <?php if (GEMINI_API_KEY === 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'): ?>
          <span class="api-status warn" role="alert">⚠ API key no configurada — edita <code class="inline">GEMINI_API_KEY</code> en el PHP</span>
          <?php else: ?>
          <span class="api-status ok">✓ API key activa · Modelo: <?= GEMINI_MODEL ?> · T: <?= TEMPERATURE ?></span>
          <?php endif; ?>
          <div class="apikey-note">Edita la constante <code class="inline">GEMINI_API_KEY</code> en la línea 24 del archivo PHP.<br>Clave gratuita: <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">aistudio.google.com/app/apikey</a></div>
        </div>
        <hr class="section-sep">
        <div class="field">
          <label>Modelos disponibles en tier gratuito</label>
          <table style="width:100%;font-size:.75rem;border-collapse:collapse">
            <thead><tr style="background:var(--surface2)">
              <th style="padding:.3rem .5rem;text-align:left;border:1px solid var(--border)">Modelo</th>
              <th style="padding:.3rem .5rem;border:1px solid var(--border)">RPM</th>
              <th style="padding:.3rem .5rem;border:1px solid var(--border)">RPD</th>
            </tr></thead>
            <tbody>
              <?php
              $modelos_info = [
                ['gemini-2.5-flash-lite','15','1.000','✓ ACTIVO'],
                ['gemini-2.5-flash','10','~250',''],
                ['gemini-2.5-pro','5','100',''],
              ];
              foreach ($modelos_info as $m): ?>
              <tr style="<?= $m[0]===GEMINI_MODEL ? 'background:var(--B-bg);font-weight:600' : '' ?>">
                <td style="padding:.3rem .5rem;border:1px solid var(--border)"><?= $m[0] ?> <?= $m[0]===GEMINI_MODEL?'<span class="pill-pass">'.$m[3].'</span>':'' ?></td>
                <td style="padding:.3rem .5rem;border:1px solid var(--border);text-align:center"><?= $m[1] ?></td>
                <td style="padding:.3rem .5rem;border:1px solid var(--border);text-align:center"><?= $m[2] ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB: PROMPT EVALUADOR -->
  <div id="tab-prompt" role="tabpanel" aria-labelledby="tbtn-prompt" class="tab-panel">
    <div class="card">
      <div class="card-head"><span class="dot" style="background:var(--eval)"></span>Prompt de evaluación (promptEval.txt)</div>
      <div class="card-body">
        <p style="font-size:.78rem;color:var(--text-dim);margin-bottom:.7rem;line-height:1.55">
          Este prompt es enviado a Gemini como evaluador independiente. Tras el texto del prompt se añaden automáticamente la consulta original y las dos respuestas (A y B). El modelo debe devolver un JSON con puntuaciones.<br>
          Los cambios se guardan en <code class="inline">promptEval.txt</code> en el directorio del programa.
        </p>
        <div class="field prompt-editor-wrap">
          <label for="inp-prompt-eval">Texto del prompt de evaluación</label>
          <textarea id="inp-prompt-eval" rows="18" aria-label="Prompt de evaluación editable"><?= $PROMPT_EVAL_INICIAL ?></textarea>
        </div>
        <div class="prompt-save-row">
          <button class="btn-secondary" onclick="guardarPromptEval()">💾 Guardar en promptEval.txt</button>
          <button class="btn-secondary" onclick="restaurarPromptEval()">↩ Restaurar por defecto</button>
          <span class="prompt-save-msg" id="prompt-save-msg" aria-live="polite"></span>
        </div>
      </div>
    </div>
  </div>

</div><!-- end left panel -->

<!-- ══ PANEL DERECHO ═══════════════════════════════════════════════════════ -->
<div style="min-width:0;overflow:hidden">

  <!-- HISTORIAL -->
  <div class="history-card" id="history-card">
    <div class="history-head">
      <span>📊 Historial de experimentos y estadísticas</span>
      <button class="btn-export" onclick="exportarCSV()">⬇ Exportar CSV</button>
    </div>
    <div class="empty-history" id="history-empty">Aún no se han ejecutado experimentos en esta sesión.</div>
    <div class="history-table-wrap" id="history-table-wrap">
      <table class="history-table" id="history-table" role="table" aria-label="Historial de experimentos">
        <thead>
          <tr>
            <th scope="col">#</th>
            <th scope="col">Consulta</th>
            <th scope="col">Sesgo A</th>
            <th scope="col">Sesgo B</th>
            <th scope="col">KW− A</th>
            <th scope="col">KW− B</th>
            <th scope="col">Pal. A</th>
            <th scope="col">Pal. B</th>
            <th scope="col">RAG</th>
            <th scope="col">Mejora léx.</th>
            <th scope="col">Punt. A</th>
            <th scope="col">Punt. B</th>
            <th scope="col">Ganador IA</th>
            <th scope="col">Aluc. A</th>
            <th scope="col">Aluc. B</th>
            <th scope="col">Timestamp</th>
          </tr>
        </thead>
        <tbody id="history-tbody"></tbody>
      </table>
    </div>
  </div>

  <!-- ÁREA DE RESULTADOS -->
  <div id="results-area" aria-live="polite">

    <div class="results-meta" id="results-meta"></div>

    <div class="resp-grid" id="resp-grid">
      <div class="resp-card" id="card-a">
        <div class="resp-head A">
          <span class="resp-head-label">Condición A — Sin RAG</span>
          <span class="resp-badge">Solo preentrenamiento</span>
        </div>
        <div class="resp-body"><div class="resp-text" id="resp-a-text">—</div></div>
        <div class="resp-foot" id="resp-a-foot"></div>
      </div>
      <div class="resp-card" id="card-b">
        <div class="resp-head B">
          <span class="resp-head-label">Condición B — Con RAG negativo</span>
          <span class="resp-badge" id="card-b-badge">—</span>
        </div>
        <div class="resp-body"><div class="resp-text" id="resp-b-text">—</div></div>
        <div class="resp-foot" id="resp-b-foot"></div>
      </div>
    </div>

    <!-- ANÁLISIS LÉXICO -->
    <div class="analysis-card" id="analysis-card">
      <div class="analysis-head" aria-label="Análisis automático por palabras clave">📈 Análisis léxico automático</div>
      <div class="analysis-body">
        <div id="veredicto-box"></div>
        <div class="stats-grid" id="stats-grid"></div>
        <hr class="section-sep">
        <div class="kw-compare" id="kw-compare">
          <div class="A">
            <h4>Condición A — Sin RAG</h4>
            <div class="kw-bar-wrap">
              <span class="kw-bar-label">Positivas</span>
              <div class="kw-bar-track"><div class="kw-bar-fill pos" id="bar-pos-a" style="width:0%"></div></div>
              <span class="kw-bar-num" id="num-pos-a">0</span>
            </div>
            <div class="kw-bar-wrap">
              <span class="kw-bar-label">Cautelosas</span>
              <div class="kw-bar-track"><div class="kw-bar-fill neg" id="bar-neg-a" style="width:0%"></div></div>
              <span class="kw-bar-num" id="num-neg-a">0</span>
            </div>
          </div>
          <div class="B">
            <h4>Condición B — Con RAG</h4>
            <div class="kw-bar-wrap">
              <span class="kw-bar-label">Positivas</span>
              <div class="kw-bar-track"><div class="kw-bar-fill pos" id="bar-pos-b" style="width:0%"></div></div>
              <span class="kw-bar-num" id="num-pos-b">0</span>
            </div>
            <div class="kw-bar-wrap">
              <span class="kw-bar-label">Cautelosas</span>
              <div class="kw-bar-track"><div class="kw-bar-fill neg" id="bar-neg-b" style="width:0%"></div></div>
              <span class="kw-bar-num" id="num-neg-b">0</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- EVALUACIÓN IA -->
    <div class="eval-card" id="eval-card">
      <div class="eval-head">🤖 Evaluación automática por IA (Gemini como juez)</div>
      <div class="eval-body">
        <p id="eval-intro" style="font-size:.82rem;color:var(--text-dim);margin-bottom:.8rem;line-height:1.55">
          Gemini actúa como evaluador independiente usando el prompt configurado en la pestaña <strong>Prompt Eval.</strong>
          Puntúa ambas respuestas en precisión, calibración, exhaustividad y mención de evidencia negativa.
        </p>
        <button class="btn-eval" id="btn-eval" onclick="evaluarConIA()" aria-live="polite">
          <span id="eval-spin" class="spinner" aria-hidden="true"></span>
          <span id="eval-text">🤖 Evaluar con IA</span>
        </button>
        <div id="eval-loading" style="display:none" role="status" aria-live="polite">
          <span class="spinner" style="display:block;width:16px;height:16px;border-color:rgba(14,116,144,.3);border-top-color:var(--eval)"></span>
          Consultando Gemini como evaluador…
        </div>
        <div id="eval-error" role="alert"></div>
        <div id="eval-results" style="display:none;margin-top:.8rem"></div>
      </div>
    </div>

  </div><!-- end results-area -->

</div><!-- end right panel -->
</div><!-- end grid-main -->
</div><!-- end wrap -->

<footer class="footer" role="contentinfo">
  <span>
    <strong>Blázquez-Ochando, M.</strong> · <a href="mailto:manublaz@ucm.es">manublaz@ucm.es</a> &nbsp;|&nbsp;
    <strong>Ovalle Perandones, M.A.</strong> · <a href="mailto:maovalle@ucm.es">maovalle@ucm.es</a> &nbsp;|&nbsp;
    <strong>Prieto Gutiérrez, J.J.</strong> · <a href="mailto:jjpg@ucm.es">jjpg@ucm.es</a><br>
    Dpto. de Biblioteconomía y Documentación · Facultad de CC. de la Documentación · UCM
  </span>
  <span>
    Modelo: <?= GEMINI_MODEL ?> · T: <?= TEMPERATURE ?> · CC-BY 4.0
  </span>
</footer>

<!-- ══ JAVASCRIPT ══════════════════════════════════════════════════════════ -->
<script>
var EJEMPLOS = <?= $EJEMPLOS_JSON ?>;
var PROMPT_DEFAULT = <?= json_encode(PROMPT_EVAL_DEFAULT, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG) ?>;

var historial  = [];
var expCount   = 0;
var lastResult = null; // guarda el último resultado para evaluación posterior

// ── TABS ───────────────────────────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
    document.querySelectorAll('.tab-panel').forEach(function(p){ p.classList.remove('active'); });
    btn.classList.add('active'); btn.setAttribute('aria-selected','true');
    document.getElementById(btn.getAttribute('aria-controls')).classList.add('active');
  });
});

// ── EJEMPLOS PRECARGADOS ──────────────────────────────────────────────────
document.getElementById('ex-grid').addEventListener('click', function(e) {
  var btn = e.target.closest('.ex-pill'); if (!btn) return;
  var ej = EJEMPLOS.find(function(x){ return x.id === btn.dataset.id; }); if (!ej) return;
  document.getElementById('inp-query').value = ej.query;
  document.getElementById('inp-rag').value   = ej.rag;
  document.getElementById('tog-rag').checked = true;
  document.querySelectorAll('.ex-pill').forEach(function(p){ p.classList.remove('selected'); });
  btn.classList.add('selected');
  // Cambiar a tab experimento si estamos en otro
  document.getElementById('tbtn-exp').click();
});

// ── EJECUTAR EXPERIMENTO ──────────────────────────────────────────────────
function ejecutarExperimento() {
  var query  = document.getElementById('inp-query').value.trim();
  var rag    = document.getElementById('inp-rag').value.trim();
  var useRag = document.getElementById('tog-rag').checked;
  if (!query) { mostrarError('inp-query','Escribe una consulta científica antes de ejecutar.'); return; }
  setBusy(true); hideResults();
  var fd = new FormData();
  fd.append('action','run_experiment'); fd.append('pregunta',query);
  fd.append('rag_texto', useRag ? rag : ''); fd.append('exp_id','exp_'+Date.now());
  fetch(window.location.href,{method:'POST',body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
      setBusy(false);
      if (!d.ok) { alert('Error API: '+d.error); return; }
      lastResult = d;
      mostrarResultados(d);
    })
    .catch(function(err){ setBusy(false); alert('Error de red: '+err.message); });
}

function mostrarError(fieldId, msg) {
  var el = document.getElementById(fieldId);
  el.style.borderColor='var(--red)'; el.focus();
  el.addEventListener('input', function(){ el.style.borderColor=''; },{once:true});
  alert(msg);
}

// ── MOSTRAR RESULTADOS ────────────────────────────────────────────────────
function mostrarResultados(d) {
  expCount++;
  document.getElementById('results-meta').innerHTML =
    '<span class="query-display">«'+esc(d.query)+'»</span>'+
    '<div class="meta-tags">'+
      '<span class="mtag mtag-model">'+d.modelo+'</span>'+
      '<span class="mtag mtag-ts">'+d.timestamp+'</span>'+
      (d.rag_fuentes>0?'<span class="mtag mtag-src">'+d.rag_fuentes+' fuentes RAG</span>':'')+
    '</div>';
  document.getElementById('resp-a-text').textContent = d.resp_a||'(sin respuesta)';
  document.getElementById('resp-a-foot').innerHTML   = '⏱ '+d.ms_a+' ms';
  if (d.resp_b) {
    document.getElementById('resp-b-text').textContent  = d.resp_b;
    document.getElementById('resp-b-foot').innerHTML    = '⏱ '+d.ms_b+' ms';
    document.getElementById('card-b-badge').textContent = d.rag_fuentes+' fuentes RAG';
    document.getElementById('card-b').style.opacity='1';
  } else {
    document.getElementById('resp-b-text').textContent='(Condición B desactivada)';
    document.getElementById('card-b').style.opacity='0.5';
  }
  var an = d.analisis;
  if (an && Object.keys(an).length) renderAnalisis(an, d);
  // Resetear evaluación IA
  document.getElementById('eval-results').style.display='none';
  document.getElementById('eval-error').style.display='none';
  document.getElementById('btn-eval').disabled = !d.resp_b;
  document.getElementById('eval-intro').style.display = d.resp_b ? 'block' : 'none';
  agregarHistorial(d, null);
  document.getElementById('results-area').style.display='block';
  document.getElementById('results-area').scrollIntoView({behavior:'smooth',block:'start'});
}

function renderAnalisis(an, d) {
  var vBox = document.getElementById('veredicto-box');
  if (an.mejora_calibracion) {
    vBox.innerHTML='<div class="veredicto pass" role="status"><span class="veredicto-icon" aria-hidden="true">✅</span><div class="veredicto-text"><strong>RAG mejoró la calibración</strong><br>'+esc(an.mejora_desc)+'</div></div>';
  } else if (d.resp_b) {
    vBox.innerHTML='<div class="veredicto warn" role="status"><span class="veredicto-icon" aria-hidden="true">⚠</span><div class="veredicto-text"><strong>Análisis léxico indeterminado</strong><br>'+esc(an.mejora_desc)+'</div></div>';
  } else {
    vBox.innerHTML='<div class="veredicto neutral" role="status"><span class="veredicto-icon" aria-hidden="true">ℹ</span><div class="veredicto-text">'+esc(an.mejora_desc)+'</div></div>';
  }
  var sg = document.getElementById('stats-grid');
  sg.innerHTML =
    statBox('Sesgo resp. A', an.sesgo_a, an.sesgo_a==='POSITIVO'?'warn':an.sesgo_a==='NEGATIVO/CAUTELOSO'?'ok':'') +
    statBox('Sesgo resp. B', an.sesgo_b||'N/A', an.sesgo_b==='NEGATIVO/CAUTELOSO'?'ok':an.sesgo_b==='POSITIVO'?'warn':'') +
    statBox('Palabras A', an.palabras_a, '') +
    statBox('Palabras B', an.palabras_b||'—', '') +
    statBox('RAG aprovechado', an.rag_aprovechado?'Sí':(d.resp_b?'Parcial':'N/A'), an.rag_aprovechado?'ok':'') +
    statBox('Advertencia B', an.tiene_advertencia_b?'Sí':(d.resp_b?'No':'N/A'), an.tiene_advertencia_b?'ok':'');
  var maxKw=Math.max(an.keywords_pos_a,an.keywords_neg_a,an.keywords_pos_b,an.keywords_neg_b,1);
  setBar('bar-pos-a','num-pos-a',an.keywords_pos_a,maxKw);
  setBar('bar-neg-a','num-neg-a',an.keywords_neg_a,maxKw);
  setBar('bar-pos-b','num-pos-b',an.keywords_pos_b,maxKw);
  setBar('bar-neg-b','num-neg-b',an.keywords_neg_b,maxKw);
}

// ── EVALUACIÓN IA ─────────────────────────────────────────────────────────
function evaluarConIA() {
  if (!lastResult || !lastResult.resp_b) { alert('Ejecuta primero un experimento con Condición B activa.'); return; }
  setEvalBusy(true);
  document.getElementById('eval-error').style.display='none';
  document.getElementById('eval-results').style.display='none';
  var fd=new FormData();
  fd.append('action','evaluate');
  fd.append('resp_a',   lastResult.resp_a);
  fd.append('resp_b',   lastResult.resp_b);
  fd.append('pregunta', lastResult.query);
  fetch(window.location.href,{method:'POST',body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
      setEvalBusy(false);
      if (!d.ok) { mostrarEvalError(d.error); return; }
      renderEvaluacion(d.evaluacion);
      actualizarHistorialConEval(expCount, d.evaluacion);
    })
    .catch(function(err){ setEvalBusy(false); mostrarEvalError('Error de red: '+err.message); });
}

function renderEvaluacion(ev) {
  var winA = ev.ganador==='A', winB = ev.ganador==='B', empate = ev.ganador==='EMPATE';
  var html = '<div class="eval-score-row">';
  html += '<div class="eval-score-box'+(winA?' winner':'')+'"><div class="eval-score-label">Puntuación A</div><div class="eval-score-val">'+ev.puntuacion_A+'/10</div>'+(winA?'<div class="eval-winner-badge">🏆 Ganador</div>':'')+'</div>';
  html += '<div class="eval-score-box'+(winB?' winner':'')+'"><div class="eval-score-label">Puntuación B</div><div class="eval-score-val">'+ev.puntuacion_B+'/10</div>'+(winB?'<div class="eval-winner-badge">🏆 Ganador</div>':'')+'</div>';
  if (empate) html += '<div class="eval-score-box winner"><div class="eval-score-label">Resultado</div><div class="eval-score-val" style="font-size:1.1rem">EMPATE</div></div>';
  html += '</div>';
  // Criterios
  if (ev.criterios) {
    html += '<div class="eval-grid">';
    var cPairs = [
      ['Precisión factual','precision_factual'],
      ['Calibración incert.','calibracion_incertidumbre'],
      ['Evid. negativa','mencion_evidencia_negativa'],
      ['Exhaustividad','exhaustividad'],
    ];
    cPairs.forEach(function(cp){
      var vA = ev.criterios[cp[1]+'_A']||0, vB = ev.criterios[cp[1]+'_B']||0;
      html += '<div class="eval-crit-box">';
      html += '<div class="eval-crit-label">'+esc(cp[0])+'</div>';
      html += '<div class="eval-crit-bars">';
      html += '<div class="eval-crit-bar"><div class="eval-crit-bar-fill fa" style="width:'+(vA*10)+'%"></div></div>';
      html += '<div class="eval-crit-bar"><div class="eval-crit-bar-fill fb" style="width:'+(vB*10)+'%"></div></div>';
      html += '<span class="eval-crit-nums">A:'+vA+' B:'+vB+'</span>';
      html += '</div></div>';
    });
    html += '</div>';
  }
  // Justificación
  if (ev.justificacion) {
    html += '<div class="eval-justif">'+esc(ev.justificacion)+'</div>';
  }
  // Alertas alucinación
  html += '<div style="margin-top:.6rem;font-size:.76rem;color:var(--text-dim)">';
  html += 'Alerta alucinación A: <span class="eval-alert '+(ev.alerta_alucinacion_A?'yes':'no')+'">'+(ev.alerta_alucinacion_A?'⚠ Sí':'✓ No')+'</span> &nbsp;';
  html += 'Alerta alucinación B: <span class="eval-alert '+(ev.alerta_alucinacion_B?'yes':'no')+'">'+(ev.alerta_alucinacion_B?'⚠ Sí':'✓ No')+'</span>';
  html += '</div>';
  var box = document.getElementById('eval-results');
  box.innerHTML = html; box.style.display='block';
  // Añadir tag en meta
  var mt = document.getElementById('results-meta');
  var tag = mt.querySelector('.mtag-eval');
  if (!tag) { mt.querySelector('.meta-tags').insertAdjacentHTML('beforeend','<span class="mtag mtag-eval">IA evaluó: B='+ev.puntuacion_B+'/10</span>'); }
  else { tag.textContent='IA evaluó: B='+ev.puntuacion_B+'/10'; }
}

function mostrarEvalError(msg) {
  var el=document.getElementById('eval-error');
  el.textContent='Error: '+msg; el.style.display='block';
}

// ── HISTORIAL ─────────────────────────────────────────────────────────────
function agregarHistorial(d, ev) {
  var an=d.analisis||{};
  historial.push({
    n:expCount, query:d.query,
    sesgo_a:an.sesgo_a||'—', sesgo_b:an.sesgo_b||'N/A',
    kw_neg_a:an.keywords_neg_a!==undefined?an.keywords_neg_a:'—',
    kw_neg_b:an.keywords_neg_b!==undefined?an.keywords_neg_b:'—',
    palabras_a:an.palabras_a||'—', palabras_b:an.palabras_b||'—',
    rag:an.rag_aprovechado?'Sí':(d.resp_b?'No':'N/A'),
    mejora:an.mejora_calibracion?'Sí':(d.resp_b?'No det.':'N/A'),
    punt_a:'—', punt_b:'—', ganador:'—',
    aluc_a:'—', aluc_b:'—',
    timestamp:d.timestamp,
  });
  renderHistorial();
}

function actualizarHistorialConEval(n, ev) {
  var r=historial.find(function(x){ return x.n===n; });
  if (!r) return;
  r.punt_a = ev.puntuacion_A; r.punt_b = ev.puntuacion_B;
  r.ganador = ev.ganador;
  r.aluc_a  = ev.alerta_alucinacion_A ? '⚠ Sí' : '✓ No';
  r.aluc_b  = ev.alerta_alucinacion_B ? '⚠ Sí' : '✓ No';
  renderHistorial();
}

function renderHistorial() {
  document.getElementById('history-empty').style.display='none';
  document.getElementById('history-table-wrap').style.display='block';
  var tbody=document.getElementById('history-tbody');
  tbody.innerHTML='';
  historial.slice().reverse().forEach(function(r) {
    var tr=document.createElement('tr');
    tr.innerHTML=
      '<td>'+r.n+'</td>'+
      '<td class="txt-q" title="'+esc(r.query)+'">'+esc(r.query.substring(0,50)+(r.query.length>50?'…':''))+'</td>'+
      '<td>'+sesgoPill(r.sesgo_a)+'</td>'+
      '<td>'+sesgoPill(r.sesgo_b)+'</td>'+
      '<td style="text-align:center">'+r.kw_neg_a+'</td>'+
      '<td style="text-align:center">'+r.kw_neg_b+'</td>'+
      '<td style="text-align:center">'+r.palabras_a+'</td>'+
      '<td style="text-align:center">'+r.palabras_b+'</td>'+
      '<td>'+boolPill(r.rag)+'</td>'+
      '<td>'+boolPill(r.mejora)+'</td>'+
      '<td style="text-align:center">'+puntuacionCell(r.punt_a)+'</td>'+
      '<td style="text-align:center">'+puntuacionCell(r.punt_b)+'</td>'+
      '<td>'+ganadorPill(r.ganador)+'</td>'+
      '<td>'+alucinCell(r.aluc_a)+'</td>'+
      '<td>'+alucinCell(r.aluc_b)+'</td>'+
      '<td style="white-space:nowrap;font-size:.68rem">'+esc(r.timestamp)+'</td>';
    tbody.appendChild(tr);
  });
}

function puntuacionCell(v) {
  if (v==='—') return '<span class="pill-na">—</span>';
  var n=parseInt(v);
  var cls = n>=7?'pill-pass':n>=4?'pill-warn':'pill-na';
  return '<span class="'+cls+'">'+v+'/10</span>';
}
function ganadorPill(g) {
  if (g==='—') return '<span class="pill-na">—</span>';
  if (g==='B') return '<span class="pill-eval">B (RAG)</span>';
  if (g==='A') return '<span class="pill-warn">A</span>';
  return '<span class="pill-na">Empate</span>';
}
function alucinCell(v) {
  if (v==='—') return '<span class="pill-na">—</span>';
  if (v.includes('Sí')) return '<span class="pill-warn">⚠ Sí</span>';
  return '<span class="pill-pass">✓ No</span>';
}
function sesgoPill(s) {
  if (s==='POSITIVO') return '<span class="pill-warn">'+esc(s)+'</span>';
  if (s==='NEGATIVO/CAUTELOSO') return '<span class="pill-pass">CAUTELOSO</span>';
  if (s==='N/A') return '<span class="pill-na">N/A</span>';
  return '<span class="pill-na">'+esc(s)+'</span>';
}
function boolPill(v) {
  if (v==='Sí') return '<span class="pill-pass">Sí</span>';
  if (v==='No') return '<span class="pill-warn">No</span>';
  return '<span class="pill-na">'+esc(v)+'</span>';
}

// ── EXPORTAR CSV ──────────────────────────────────────────────────────────
function exportarCSV() {
  if (!historial.length) { alert('No hay experimentos para exportar.'); return; }
  var cols=['#','Consulta','Sesgo_A','Sesgo_B','KW_neg_A','KW_neg_B','Palabras_A','Palabras_B','RAG_usado','Mejora_lexica','Puntuacion_A','Puntuacion_B','Ganador_IA','Alucinacion_A','Alucinacion_B','Timestamp'];
  var rows=historial.map(function(r){
    return [r.n,'"'+r.query.replace(/"/g,'""')+'"',r.sesgo_a,r.sesgo_b,r.kw_neg_a,r.kw_neg_b,r.palabras_a,r.palabras_b,r.rag,r.mejora,r.punt_a,r.punt_b,r.ganador,r.aluc_a,r.aluc_b,r.timestamp].join(',');
  });
  var csv=cols.join(',')+'\n'+rows.join('\n');
  var blob=new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8;'});
  var a=document.createElement('a'); a.href=URL.createObjectURL(blob);
  a.download='experimento_rag_'+new Date().toISOString().slice(0,10)+'.csv'; a.click();
}

// ── PROMPT EVAL ───────────────────────────────────────────────────────────
function guardarPromptEval() {
  var txt=document.getElementById('inp-prompt-eval').value.trim();
  if (!txt) { alert('El prompt no puede estar vacío.'); return; }
  var fd=new FormData(); fd.append('action','save_prompt_eval'); fd.append('prompt_eval',txt);
  fetch(window.location.href,{method:'POST',body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
      var msg=document.getElementById('prompt-save-msg');
      if (d.ok) { msg.textContent='✓ Guardado correctamente'; msg.className='prompt-save-msg'; }
      else      { msg.textContent='✗ Error al guardar'; msg.className='prompt-save-msg err'; }
      msg.style.display='block';
      setTimeout(function(){ msg.style.display='none'; }, 3000);
    });
}
function restaurarPromptEval() {
  if (!confirm('¿Restaurar el prompt por defecto? Se perderán los cambios guardados.')) return;
  document.getElementById('inp-prompt-eval').value=PROMPT_DEFAULT;
}

// ── DRAG & DROP TXT ───────────────────────────────────────────────────────
var dropZone=document.getElementById('drop-zone'), fileInput=document.getElementById('file-input');
dropZone.addEventListener('dragover', function(e){ e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave',function(){ dropZone.classList.remove('drag-over'); });
dropZone.addEventListener('drop',     function(e){ e.preventDefault(); dropZone.classList.remove('drag-over'); procesarArchivos(e.dataTransfer.files); });
fileInput.addEventListener('change',  function(){ procesarArchivos(fileInput.files); });
dropZone.addEventListener('keydown',  function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); fileInput.click(); } });

function procesarArchivos(files) {
  Array.from(files).forEach(function(file){
    if (!file.name.endsWith('.txt')) return;
    var item=addFileQueueItem(file.name);
    var reader=new FileReader();
    reader.onload=function(e){
      var fd=new FormData(); fd.append('action','parse_txt'); fd.append('contenido',e.target.result);
      fetch(window.location.href,{method:'POST',body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (d.ok && d.bloques.length) {
            item.status.textContent=d.total+' caso'+(d.total>1?'s':'');
            item.status.className='fq-status fq-ok';
            item.useBtn.style.display='inline-block';
            item.useBtn.onclick=function(){
              document.getElementById('inp-query').value=d.bloques[0].query;
              document.getElementById('inp-rag').value=d.bloques[0].rag;
              document.getElementById('tog-rag').checked=!!d.bloques[0].rag;
              document.getElementById('tbtn-exp').click();
              if(d.total>1) alert('Se cargó el caso 1 de '+d.total+'. Ejecuta y vuelve a "Usar" para el siguiente.');
            };
          } else { item.status.textContent='Formato no reconocido'; item.status.className='fq-status fq-err'; }
        }).catch(function(){ item.status.textContent='Error'; item.status.className='fq-status fq-err'; });
    };
    reader.readAsText(file,'UTF-8');
  });
}

function addFileQueueItem(name) {
  var q=document.getElementById('file-queue'), div=document.createElement('div');
  div.className='fq-item';
  div.innerHTML='<span class="fq-name" title="'+esc(name)+'">'+esc(name)+'</span>'+
    '<span style="display:flex;align-items:center;gap:.35rem">'+
      '<span class="fq-status fq-load" id="fqs-'+Date.now()+'">Leyendo…</span>'+
      '<button class="fq-use" style="display:none">Usar</button>'+
    '</span>';
  q.appendChild(div);
  return { status:div.querySelector('[id^=fqs-]'), useBtn:div.querySelector('.fq-use') };
}

// ── UTILS ─────────────────────────────────────────────────────────────────
function setBusy(busy) {
  var btn=document.getElementById('btn-run');
  btn.disabled=busy;
  document.getElementById('btn-spin').style.display=busy?'block':'none';
  document.getElementById('btn-text').textContent=busy?'Consultando Gemini…':'▶ Ejecutar experimento';
}
function setEvalBusy(busy) {
  var btn=document.getElementById('btn-eval');
  btn.disabled=busy;
  document.getElementById('eval-spin').style.display=busy?'block':'none';
  document.getElementById('eval-text').textContent=busy?'Evaluando…':'🤖 Evaluar con IA';
  document.getElementById('eval-loading').style.display=busy?'flex':'none';
}
function hideResults() { document.getElementById('results-area').style.display='none'; }
function statBox(label, val, cls) {
  return '<div class="stat-box"><div class="stat-label">'+esc(label)+'</div><div class="stat-val '+cls+'">'+esc(String(val))+'</div></div>';
}
function setBar(bId,nId,val,max) {
  document.getElementById(bId).style.width=Math.round((val/max)*100)+'%';
  document.getElementById(nId).textContent=val;
}
function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
