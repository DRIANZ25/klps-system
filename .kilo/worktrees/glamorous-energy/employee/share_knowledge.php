<?php
$page_title = 'Share Knowledge';
require_once __DIR__ . '/../partials/header.php';

$currentUserId = $_SESSION['user']['id'] ?? 0;

// Get categories and departments for dropdowns
$categories = [];
$departments = [];
try {
    $categories = db()->query('SELECT id, name FROM knowledge_categories ORDER BY name')->fetchAll();
    $departments = db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
} catch (PDOException $e) {}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $department_id = (int)($_POST['department_id'] ?? 0);
    $problem = trim($_POST['problem'] ?? '');
    $cause = trim($_POST['cause'] ?? '');
    $solution = trim($_POST['solution'] ?? '');
    $procedure_steps = trim($_POST['procedure_steps'] ?? '');
    $best_practice = trim($_POST['best_practice'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    
    if ($title && $problem && $solution) {
        try {
            $stmt = db()->prepare(
                'INSERT INTO knowledge (category_id, department_id, created_by, title, problem, cause, solution, procedure_steps, best_practice, tags, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active")'
            );
            $stmt->execute([
                $category_id ?: null, 
                $department_id ?: null, 
                $currentUserId, 
                $title, 
                $problem, 
                $cause, 
                $solution, 
                $procedure_steps, 
                $best_practice, 
                $tags
            ]);
            $_SESSION['success'] = '✅ Article created successfully!';
            redirect('knowledge.php');
        } catch (PDOException $e) {
            $error = 'Failed to create article: ' . $e->getMessage();
        }
    } else {
        $error = 'Title, Problem, and Solution are required.';
    }
}
?>
<style>
:root {
    --cyan: #00f0ff;
    --violet: #8b7cf6;
    --gold: #f6c453;
    --text-dim: #8892b0;
    --border: rgba(255,255,255,0.07);
}
.share-page {
    background: radial-gradient(ellipse at 20% -10%, #1a2140 0%, #03050a 45%, #050710 100%);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(0,240,255,0.08);
    max-width: 800px;
    margin: 0 auto;
}
.share-page h1 {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.8rem;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}
.share-page h1 i {
    color: #f6c453;
}
.share-page .subtitle {
    color: #8892b0;
    margin-bottom: 2rem;
}
.share-page .form-group {
    margin-bottom: 1.25rem;
}
.share-page .form-group label {
    display: block;
    color: #8892b0;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 0.4rem;
}
.share-page .form-group label .required {
    color: #ef4444;
}
.share-page .form-control {
    width: 100%;
    padding: 0.7rem 1rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    color: #fff;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    box-sizing: border-box;
}
.share-page .form-control:focus {
    outline: none;
    border-color: rgba(0,240,255,0.3);
    background: rgba(255,255,255,0.06);
}
.share-page .form-control::placeholder {
    color: #5c6f94;
}
.share-page .form-control textarea {
    resize: vertical;
    font-family: inherit;
}
.share-page .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.share-page .form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}
.share-page .btn-submit {
    padding: 0.75rem 2rem;
    background: linear-gradient(135deg, #00f0ff, #8b7cf6);
    border: none;
    border-radius: 10px;
    color: #0a0e1a;
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.share-page .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(0,240,255,0.3);
}
.share-page .btn-cancel {
    padding: 0.75rem 2rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    color: #8892b0;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.share-page .btn-cancel:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}
.share-page .error-message {
    padding: 1rem 1.25rem;
    background: rgba(255,0,60,0.08);
    border: 1px solid rgba(255,0,60,0.2);
    border-radius: 10px;
    color: #ff7c93;
    margin-bottom: 1.5rem;
}
.share-page .success-message {
    padding: 1rem 1.25rem;
    background: rgba(0,255,156,0.08);
    border: 1px solid rgba(0,255,156,0.2);
    border-radius: 10px;
    color: #00ff9c;
    margin-bottom: 1.5rem;
}
@media (max-width: 768px) {
    .share-page { padding: 1rem; }
    .share-page .form-row { grid-template-columns: 1fr; }
    .share-page .form-actions { flex-direction: column; }
    .share-page .form-actions .btn-submit,
    .share-page .form-actions .btn-cancel { justify-content: center; }
}
</style>

<div class="share-page">
    <h1><i class="fas fa-plus-circle"></i> Share Knowledge</h1>
    <p class="subtitle">Share your expertise with the community. Help others solve problems faster.</p>

    <?php if (isset($error)): ?>
        <div class="error-message"><i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>Title <span class="required">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="Enter a clear, descriptive title" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control">
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department_id" class="form-control">
                    <option value="">Select a department</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Problem <span class="required">*</span></label>
            <textarea name="problem" class="form-control" rows="3" placeholder="Describe the problem clearly" required></textarea>
        </div>

        <div class="form-group">
            <label>Root Cause</label>
            <textarea name="cause" class="form-control" rows="2" placeholder="What caused the problem?"></textarea>
        </div>

        <div class="form-group">
            <label>Solution <span class="required">*</span></label>
            <textarea name="solution" class="form-control" rows="3" placeholder="How did you solve it?" required></textarea>
        </div>

        <div class="form-group">
            <label>Procedure Steps</label>
            <textarea name="procedure_steps" class="form-control" rows="4" placeholder="Step-by-step instructions (one per line)"></textarea>
        </div>

        <div class="form-group">
            <label>Best Practice</label>
            <textarea name="best_practice" class="form-control" rows="2" placeholder="What should others keep in mind?"></textarea>
        </div>

        <div class="form-group">
            <label>Tags</label>
            <input type="text" name="tags" class="form-control" placeholder="e.g. troubleshooting, network, windows (comma separated)">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Publish Article</button>
            <a href="knowledge.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>