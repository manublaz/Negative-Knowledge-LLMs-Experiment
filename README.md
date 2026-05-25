# Efecto del Conocimiento Negativo en LLMs

<div align="center">

![NRR-Experiment](https://img.shields.io/badge/NRR--Experiment-v3.0-4a90d9?style=for-the-badge&logo=flask&logoColor=white)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Gemini API](https://img.shields.io/badge/Google_Gemini_API-2.5--flash--lite-4285F4?style=for-the-badge&logo=google&logoColor=white)](https://aistudio.google.com)
[![RAG](https://img.shields.io/badge/RAG-Retrieval--Augmented_Generation-orange?style=for-the-badge)](https://en.wikipedia.org/wiki/Retrieval-augmented_generation)

**Herramienta de demostración experimental para evaluar el impacto del conocimiento negativo curado (resultados negativos científicos) en la calibración de respuestas de modelos de lenguaje de gran escala (LLMs), mediante Retrieval-Augmented Generation (RAG) y evaluación automática por IA.**

</div>

---

## 📋 Descripción

El experimento pone a prueba la hipótesis central del artículo: **la inclusión de evidencia negativa curada (resultados de replicación fallida, ensayos clínicos nulos, meta-análisis refutatorios) como contexto RAG en un LLM produce respuestas científicamente más calibradas, exhaustivas y cautelosas** que las generadas exclusivamente a partir del corpus de preentrenamiento, que refleja el sesgo positivo estructural de la literatura científica publicada.

El sistema no requiere ajuste fino (*fine-tuning*) del modelo. Demuestra que una arquitectura RAG con un corpus de resultados negativos curados —el *Negative Results Repository* (NRR) propuesto en el artículo— puede mejorar inmediatamente la fiabilidad de los asistentes de IA científica.

---

## ✨ Características principales

- **Experimento de dos condiciones controladas**: Condición A (solo preentrenamiento) vs. Condición B (preentrenamiento + RAG negativo), con la misma consulta, el mismo modelo y la misma temperatura.
- **5 casos de uso científicos precargados** con query y corpus RAG (bexaroteno/Alzheimer, vitamina E, ivermectina/COVID-19, células madre, homeopatía).
- **Análisis léxico automático**: detección de sesgo (POSITIVO / NEGATIVO/CAUTELOSO / NEUTRO), recuento de palabras clave, comparativa de exhaustividad.
- **Evaluación automática por IA (Gemini como juez)**: tercera llamada independiente que puntúa ambas respuestas (0–10) en precisión factual, calibración de incertidumbre, mención de evidencia negativa y exhaustividad. Detecta alertas de alucinación.
- **Prompt de evaluación editable en caliente** desde la pestaña *Prompt Eval.*, guardado en `promptEval.txt`.
- **Carga de casos externos** mediante drag & drop de archivos `.txt` con formato estandarizado (soporte multi-bloque con separador `---`).
- **Historial de sesión con 16 columnas** (métricas léxicas + puntuaciones IA + ganador + alertas de alucinación) y **exportación CSV** con BOM UTF-8.
- **Interfaz light responsiva** con paleta accesible WCAG AA y pestañas organizativas (Experimento / Archivos TXT / Config / Prompt Eval.).
- **Un único archivo PHP**, sin frameworks, sin base de datos, sin dependencias externas. Desplegable en cualquier servidor PHP 7.4+.

---

## 🏗️ Arquitectura del experimento

```
┌─────────────────────────────────────────────────────────────┐
│                    CONSULTA CIENTÍFICA                      │
│                  (misma query A y B)                        │
└───────────────────┬─────────────────────┬───────────────────┘
                    │                     │
          ┌─────────▼──────┐   ┌──────────▼──────────────┐
          │  CONDICIÓN A   │   │     CONDICIÓN B         │
          │  Sin RAG       │   │  + Corpus RAG negativo  │
          │  (preentren.)  │   │  (NRR curado)           │
          └─────────┬──────┘   └──────────┬──────────────┘
                    │                     │
          ┌─────────▼─────────────────────▼───────────────┐
          │           Google Gemini API                   │
          │        (mismo modelo, T=0.2)                  │
          └──────────────────┬────────────────────────────┘
                             │
              ┌──────────────▼──────────────┐
              │    ANÁLISIS LÉXICO AUTO.    │
              │  (sesgo, KW, palabras...)   │
              └──────────────┬──────────────┘
                             │
              ┌──────────────▼──────────────┐
              │  EVALUACIÓN IA (Gemini      │
              │  como juez independiente)   │
              │  → JSON con puntuaciones    │
              └─────────────────────────────┘
```

---

## 🚀 Instalación y uso

### Requisitos

- PHP 7.4 o superior con extensión **cURL** habilitada.
- Conexión a internet para llamadas a la API de Google Gemini.
- API key gratuita de Google AI Studio.

### Pasos

1. **Clona el repositorio** o descarga los dos archivos:
   ```bash
   git clone https://github.com/manublaz/nrr-experiment-rag
   cd nrr-experiment-rag
   ```

2. **Coloca ambos archivos** en el mismo directorio de tu servidor PHP:
   ```
   experimento_rag.php
   promptEval.txt
   ```

3. **Obtén una API key gratuita** en [aistudio.google.com/app/apikey](https://aistudio.google.com/app/apikey).

4. **Edita la línea 24** de `experimento_rag.php`:
   ```php
   define('GEMINI_API_KEY', 'TU_API_KEY_AQUÍ');
   ```

5. **Accede desde el navegador**:
   ```
   http://tu-servidor/experimento_rag.php
   ```

### Cambiar el modelo Gemini

Edita la constante `GEMINI_MODEL` en la línea 33 (modelos disponibles en tier gratuito, mayo 2026):

| Modelo | RPM | RPD | Notas |
|--------|-----|-----|-------|
| `gemini-2.5-flash-lite` | 15 | 1.000 | ✅ Recomendado |
| `gemini-2.5-flash` | 10 | ~250 | Más capaz |
| `gemini-2.5-pro` | 5 | 100 | Mayor razonamiento |

> ⚠️ `gemini-2.0-flash` fue retirado el 3 de marzo de 2026 (cuota = 0). No usar.

---

## 📁 Formato de archivo TXT

Para cargar casos propios mediante la pestaña **Archivos TXT**, usa este formato:

```
QUERY: ¿Tu pregunta científica aquí?
RAG:
Autor et al. (año) — Revista:
Texto del resultado negativo o fracaso de replicación.

Autor2 et al. (año) — Revista:
Otro fragmento de evidencia negativa curada.
```

Para incluir múltiples casos en un mismo archivo, sepáralos con una línea `---`:

```
QUERY: ¿Primera pregunta?
RAG:
...

---

QUERY: ¿Segunda pregunta?
RAG:
...
```

---

## ✏️ Personalización del prompt de evaluación

El archivo `promptEval.txt` contiene el prompt que Gemini usa como evaluador independiente. Puedes editarlo:

- **Desde la interfaz web**: pestaña *Prompt Eval.* → edita y guarda.
- **Directamente**: edita `promptEval.txt` con cualquier editor de texto.

El prompt debe instruir al modelo para devolver un objeto **JSON válido** con la estructura:

```json
{
  "puntuacion_A": 7,
  "puntuacion_B": 9,
  "ganador": "B",
  "diferencia": 2,
  "criterios": {
    "precision_factual_A": 8, "precision_factual_B": 10,
    "calibracion_incertidumbre_A": 7, "calibracion_incertidumbre_B": 9,
    "mencion_evidencia_negativa_A": 5, "mencion_evidencia_negativa_B": 10,
    "exhaustividad_A": 7, "exhaustividad_B": 9
  },
  "justificacion": "Texto en español, máx. 3 oraciones.",
  "alerta_alucinacion_A": false,
  "alerta_alucinacion_B": false
}
```

---

## 📊 Historial y exportación de datos

Cada experimento ejecutado en la sesión queda registrado en la tabla con las siguientes columnas:

| Columna | Descripción |
|---------|-------------|
| # | Número de experimento en la sesión |
| Consulta | Query científica (truncada) |
| Sesgo A / B | POSITIVO / NEGATIVO/CAUTELOSO / NEUTRO |
| KW− A / B | Palabras clave cautelosas detectadas |
| Pal. A / B | Longitud de la respuesta (palabras) |
| RAG | ¿El modelo aprovechó el corpus RAG? |
| Mejora léx. | Veredicto del análisis léxico automático |
| Punt. A / B | Puntuación IA del evaluador (0–10) |
| Ganador IA | A / B / EMPATE según Gemini evaluador |
| Aluc. A / B | Alerta de alucinación detectada por IA |
| Timestamp | Fecha y hora del experimento |

El botón **⬇ Exportar CSV** genera un archivo con BOM UTF-8, directamente compatible con Excel y LibreOffice Calc.

---

## 🔬 Contexto de investigación

Este experimento es la demostración práctica de la propuesta central del artículo:

> *«No es necesario reentrenar un modelo para mejorar su calibración científica; basta con que los sistemas de Recuperación de Información sirvan evidencia negativa curada junto a los resultados positivos tradicionales.»*

El artículo argumenta que el sesgo de publicación positiva —documentado por Fanelli (2012) como un crecimiento del 70,2% al 85,9% en artículos con resultados positivos entre 1990 y 2007— distorsiona los corpus de entrenamiento de los LLMs, produciendo asistentes científicos que sobreestiman sistemáticamente la eficacia de hipótesis cuya evidencia es contradictoria o nula. La solución estructural es el *Negative Results Repository* (NRR): un repositorio global de acceso abierto con metadatos CERTAIN, revisión por pares adaptada y métricas N-Citations, cuyos corpus exportables podrían nutrir sistemas RAG como el aquí demostrado.

---

<div align="center">

Este experimento representa una contribución a la intersección entre la Inteligencia Artificial y las Ciencias de la Documentación, demostrando que la gestión del conocimiento negativo —la documentación sistemática de los fracasos científicos— es un requisito funcional para construir sistemas de IA científica fiables, calibrados y con espíritu crítico.

</div>

---

## 📚 Citas y referencias

Si utilizas este experimento o el código en tu investigación o docencia, por favor cítalo de la siguiente forma:

> Blázquez-Ochando, M., Ovalle Perandones, M. A., & Prieto Gutiérrez, J. J. (2026). *NRR-Experiment-RAG: Herramienta de demostración experimental del efecto del conocimiento negativo en LLMs mediante RAG* [Software]. GitHub. https://github.com/manublaz/Negative-Knowledge-LLMs-Experiment

