"""
RiceGuard — Flask AI API Server
Serves rice disease predictions using 3 models (MobileNetV2, DenseNet201, EfficientNetV2S).
Returns inference time with every prediction.
Runs on port 5000.
"""

import os
from datetime import datetime
from flask import Flask, request, jsonify
from flask_cors import CORS
import mysql.connector

from models import predict as model_predict, get_available_models, AVAILABLE_MODELS

app = Flask(__name__)
CORS(app)

# Upload folder for received images
UPLOAD_FOLDER = os.path.join(os.path.dirname(__file__), 'uploads')
os.makedirs(UPLOAD_FOLDER, exist_ok=True)

# Database config (same as PHP)
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'rice_guard_db',
}


def get_db():
    """Get a MySQL database connection."""
    return mysql.connector.connect(**DB_CONFIG)


# ── Routes ─────────────────────────────────────────────────

@app.route('/', methods=['GET'])
def index():
    return jsonify({
        'service': 'RiceGuard AI API',
        'version': '2.0.0',
        'status': 'running',
        'models': AVAILABLE_MODELS,
        'endpoints': {
            'GET /models': 'List available AI models with accuracy info',
            'POST /predict': 'Analyze an image (returns inference_time_ms)',
            'POST /predict/compare': 'Compare all 3 models on same image',
        }
    })


@app.route('/models', methods=['GET'])
def list_models():
    """Return info on all available AI models."""
    return jsonify(get_available_models())


@app.route('/predict', methods=['POST'])
def predict():
    """
    Analyze an uploaded rice leaf image for disease.

    Form params:
        - image: file (required) — the leaf photo
        - model: string (required) — 'mobilenetv2', 'densenet201', or 'efficientnetv2s'
        - report_id: int (optional) — link to an existing disease report

    Returns:
        - disease_name, confidence_score, inference_time_ms, top_predictions, etc.
    """
    # Validate image
    if 'image' not in request.files:
        return jsonify({'error': 'No image file provided'}), 400

    image_file = request.files['image']
    if image_file.filename == '':
        return jsonify({'error': 'Empty filename'}), 400

    # Validate model selection
    model_name = request.form.get('model', '').lower()
    if not model_name:
        return jsonify({'error': f'Model parameter is required. Available: {AVAILABLE_MODELS}'}), 400

    # Read image data
    image_data = image_file.read()
    if len(image_data) == 0:
        return jsonify({'error': 'Image file is empty'}), 400

    # Save image
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    filename = f"{model_name}_{timestamp}_{image_file.filename}"
    filepath = os.path.join(UPLOAD_FOLDER, filename)
    with open(filepath, 'wb') as f:
        f.write(image_data)

    # Run prediction (includes inference_time_ms)
    try:
        result = model_predict(model_name, image_data)
    except ValueError as e:
        return jsonify({'error': str(e)}), 400

    # Save to database if report_id provided
    report_id = request.form.get('report_id')
    if report_id:
        try:
            db = get_db()
            cursor = db.cursor()
            cursor.execute(
                """INSERT INTO tbl_ai_diagnosis
                   (report_id, disease_id, confidence_score, model_version)
                   VALUES (%s, %s, %s, %s)""",
                (int(report_id), result['disease_id'],
                 result['confidence_score'], result['model_version'])
            )
            db.commit()
            result['ai_diagnosis_id'] = cursor.lastrowid
            cursor.close()
            db.close()
        except Exception as e:
            result['db_warning'] = f'Failed to save diagnosis: {str(e)}'

    result['model_used'] = model_name
    result['image_saved'] = filename

    return jsonify(result), 200


@app.route('/predict/compare', methods=['POST'])
def compare_models():
    """
    Run all 3 models on the same image and return comparative results.
    Each prediction includes its own inference_time_ms.

    Form params:
        - image: file (required) — the leaf photo
        - report_id: int (optional) — link to an existing disease report
    """
    if 'image' not in request.files:
        return jsonify({'error': 'No image file provided'}), 400

    image_file = request.files['image']
    image_data = image_file.read()

    if len(image_data) == 0:
        return jsonify({'error': 'Image file is empty'}), 400

    results = []
    total_time_ms = 0

    for model_key in AVAILABLE_MODELS:
        prediction = model_predict(model_key, image_data)
        prediction['model_used'] = model_key
        total_time_ms += prediction.get('inference_time_ms', 0)
        results.append(prediction)

    # Sort by confidence
    results.sort(key=lambda x: x['confidence_score'], reverse=True)

    return jsonify({
        'predictions': results,
        'best_prediction': results[0],
        'total_inference_time_ms': round(total_time_ms, 2),
    }), 200


# ── Run Server ─────────────────────────────────────────────
if __name__ == '__main__':
    print("=" * 50)
    print("  RiceGuard AI API Server v2.0")
    print("  Running on http://localhost:5000")
    print(f"  Models: {', '.join(AVAILABLE_MODELS)}")
    print("  Inference time tracked per prediction")
    print("=" * 50)
    app.run(host='0.0.0.0', port=5000, debug=True)
