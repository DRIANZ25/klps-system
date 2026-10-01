<?php
$page_title = 'Share Knowledge';
$page_css   = ['employee-share_knowledge.css'];
$page_js    = ['employee-share_knowledge.js'];
require_once __DIR__ . '/../config/config.php';
require_login();

// IDs are prefixed strings ('U001'), so an int cast here collapses the user id to 0
// and breaks both the department pre-fill and the created_by foreign key.
$uid = id_param($_SESSION['user']['users_id'] ?? '');
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

$categories = $departments = [];
$myDept = '';
try {
    $categories  = db()->query('SELECT knowledge_categories_id AS id, name FROM knowledge_categories ORDER BY name')->fetchAll();
    $departments = db()->query('SELECT departments_id AS id, name FROM departments ORDER BY name')->fetchAll();
    $s = db()->prepare('SELECT department_id FROM users WHERE users_id = ?');
    $s->execute([$uid]);
    $myDept = id_param($s->fetchColumn());
} catch (PDOException $e) {}

function sk_tags(string $raw): string {
    $out = [];
    foreach (explode(',', $raw) as $t) {
        $t = mb_strtolower(trim(preg_replace('/\s+/', ' ', $t)));
        if ($t !== '' && mb_strlen($t) <= 30 && !in_array($t, $out, true)) $out[] = $t;
        if (count($out) >= 10) break;
    }
    return implode(', ', $out);
}

$f = ['title' => '', 'category_id' => '', 'department_id' => $myDept, 'problem' => '', 'cause' => '',
      'solution' => '', 'procedure_steps' => '', 'best_practice' => '', 'tags' => ''];
$errors  = [];
$isFresh = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isFresh = false;
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    $f['tags'] = sk_tags($f['tags']);
    $asDraft   = ($_POST['submit_as'] ?? '') === 'draft';

    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $errors['form'] = 'Your session expired. Please submit again.';

    if ($f['title'] === '')                  $errors['title'] = 'Give your article a title.';
    elseif (mb_strlen($f['title']) > 200)    $errors['title'] = 'Keep the title under 200 characters.';
    if ($f['category_id'] === '')            $errors['category_id'] = 'Choose a category.';
    if (!$asDraft) {
        if (mb_strlen($f['problem']) < 10)   $errors['problem']  = 'Describe the problem in at least 10 characters.';
        if (mb_strlen($f['solution']) < 10)  $errors['solution'] = 'Explain the solution in at least 10 characters.';
    }

    $cat  = $f['category_id'];   if (!in_array($cat,  array_column($categories,  'id'), true)) $cat  = null;
    $dept = $f['department_id']; if (!in_array($dept, array_column($departments, 'id'), true)) $dept = null;

    // A blank or unknown selection falls back to the author's own department, so an
    // article is never stored with department_id = NULL just because the field was left alone.
    $dept = $dept ?: $myDept;

    if (!$errors) {
        try {
            $status = $asDraft ? 'draft' : 'active';
            $knowledgeId = new_id('knowledge');
            db()->prepare(
                'INSERT INTO knowledge (knowledge_id, category_id, department_id, created_by, title, problem, cause, solution, procedure_steps, best_practice, tags, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$knowledgeId, $cat, $dept, $uid, $f['title'], $f['problem'], $f['cause'], $f['solution'],
                        $f['procedure_steps'], $f['best_practice'], $f['tags'], $status]);

            try { db()->prepare('INSERT INTO activity_logs (activity_logs_id, user_id, action, details) VALUES (?, ?, ?, ?)')
                      ->execute([new_id('activity_logs'), $uid, 'create_knowledge', mb_substr($f['title'], 0, 80)]); } catch (PDOException $e) {}

            $_SESSION['success'] = $asDraft ? 'Draft saved. Finish it from My Contributions.' : 'Article published. Thank you for sharing!';
            redirect($asDraft ? 'my_contributions.php' : 'knowledge.php');
        } catch (PDOException $e) {
            error_log('Share knowledge failed: ' . $e->getMessage());
            $errors['form'] = 'We could not save your article. Please try again.';
        }
    }
}

$stepLines = array_values(array_filter(array_map('trim', preg_split('/\R/', $f['procedure_steps']))));
$err = fn(string $k) => isset($errors[$k]) ? '<p class="sk-err" role="alert"><i class="fas fa-circle-exclamation"></i> ' . e($errors[$k]) . '</p>' : '';
$bad = fn(string $k) => isset($errors[$k]) ? ' is-bad' : '';

require_once __DIR__ . '/../partials/header.php';
?>
<div class="sk-page" data-uid="<?= $uid ?>" data-fresh="<?= $isFresh ? '1' : '0' ?>">
    <canvas class="sk-bg" id="skBg" aria-hidden="true"></canvas>

    <div class="sk-wrap">
        <header class="sk-hero">
            <span class="sk-hero-ico"><i class="fas fa-lightbulb"></i></span>
            <div>
                <h1>Share knowledge</h1>
                <p>Turn a problem you solved into a guide your team can reuse. It takes about five minutes.</p>
            </div>
        </header>

        <nav class="sk-steps" aria-label="Form progress">
            <a href="#s1" data-for="s1"><b>1</b> Basics</a>
            <a href="#s2" data-for="s2"><b>2</b> Problem</a>
            <a href="#s3" data-for="s3"><b>3</b> Solution</a>
            <a href="#s4" data-for="s4"><b>4</b> Tags</a>
        </nav>

        <div class="sk-restore" id="skRestore" hidden>
            <i class="fas fa-clock-rotate-left"></i>
            <span>You have an unfinished draft on this device.</span>
            <button type="button" id="skRestoreYes">Restore it</button>
            <button type="button" id="skRestoreNo" class="ghost">Discard</button>
        </div>

        <?php if (isset($errors['form'])): ?><div class="sk-alert" role="alert"><i class="fas fa-triangle-exclamation"></i> <?= e($errors['form']) ?></div><?php endif; ?>

        <div class="sk-layout">
            <form class="sk-form" id="skForm" method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                <!-- 1 Basics -->
                <section class="sk-sec" id="s1">
                    <h2><span>1</span> The basics</h2>
                    <div class="sk-field<?= $bad('title') ?>">
                        <label for="fTitle">Title <em>required</em></label>
                        <input id="fTitle" name="title" type="text" maxlength="200" data-max="200" value="<?= e($f['title']) ?>" placeholder="e.g. Printer shows offline after Windows update">
                        <?= $err('title') ?>
                    </div>
                    <div class="sk-row2">
                        <div class="sk-field<?= $bad('category_id') ?>">
                            <label for="fCat">Category <em>required</em></label>
                            <select id="fCat" name="category_id">
                                <option value="">Choose a category</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= e($c['id']) ?>" <?= $c['id'] === $f['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('category_id') ?>
                        </div>
                        <div class="sk-field">
                            <label for="fDept">Department</label>
                            <select id="fDept" name="department_id">
                                <option value="">Choose a department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= e($d['id']) ?>" <?= $d['id'] === $f['department_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- 2 Problem -->
                <section class="sk-sec" id="s2">
                    <h2><span>2</span> What went wrong?</h2>
                    <div class="sk-field<?= $bad('problem') ?>">
                        <label for="fProblem">Problem <em>required</em></label>
                        <textarea id="fProblem" name="problem" rows="3" placeholder="What did you see? Include error messages and when it happens."><?= e($f['problem']) ?></textarea>
                        <?= $err('problem') ?>
                    </div>
                    <div class="sk-field">
                        <label for="fCause">Root cause</label>
                        <textarea id="fCause" name="cause" rows="2" placeholder="Why did it happen?"><?= e($f['cause']) ?></textarea>
                    </div>
                </section>

                <!-- 3 Solution -->
                <section class="sk-sec" id="s3">
                    <h2><span>3</span> How you fixed it</h2>
                    <div class="sk-field<?= $bad('solution') ?>">
                        <label for="fSolution">Solution <em>required</em></label>
                        <textarea id="fSolution" name="solution" rows="3" placeholder="Summarise the fix in a few sentences."><?= e($f['solution']) ?></textarea>
                        <?= $err('solution') ?>
                    </div>
                    <div class="sk-field">
                        <label for="skSteps">Step-by-step procedure</label>
                        <textarea id="skSteps" name="procedure_steps" rows="4" placeholder="One step per line"><?= e($f['procedure_steps']) ?></textarea>
                        <ol class="sk-stepsui" id="skStepsUI" hidden data-initial="<?= e(json_encode($stepLines)) ?>"></ol>
                        <button type="button" class="sk-add" id="skAddStep" hidden><i class="fas fa-plus"></i> Add step</button>
                    </div>
                    <div class="sk-field">
                        <label for="fBest">Best practice</label>
                        <textarea id="fBest" name="best_practice" rows="2" placeholder="What should others remember next time?"><?= e($f['best_practice']) ?></textarea>
                    </div>
                </section>

                <!-- 4 Tags -->
                <section class="sk-sec" id="s4">
                    <h2><span>4</span> Make it easy to find</h2>
                    <div class="sk-field">
                        <label for="skTags">Tags <small>up to 10</small></label>
                        <input id="skTags" name="tags" type="text" value="<?= e($f['tags']) ?>" placeholder="troubleshooting, network, windows">
                        <div class="sk-tagbox" id="skTagBox" hidden></div>
                        <div class="sk-suggest" id="skSuggest" hidden>
                            <span>Quick add:</span>
                            <?php foreach (['troubleshooting', 'how-to', 'network', 'hardware', 'software', 'security', 'process'] as $t): ?>
                                <button type="button" data-tag="<?= $t ?>">+ <?= $t ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <div class="sk-actions">
                    <button type="submit" name="submit_as" value="publish" class="sk-btn primary" id="skPublish"><i class="fas fa-paper-plane"></i> Publish article</button>
                    <button type="submit" name="submit_as" value="draft" class="sk-btn"><i class="fas fa-floppy-disk"></i> Save as draft</button>
                    <a href="knowledge.php" class="sk-btn ghost"><i class="fas fa-xmark"></i> Cancel</a>
                    <span class="sk-saved" id="skSaved" aria-live="polite"></span>
                    <span class="sk-hint">Ctrl + Enter to publish</span>
                </div>
            </form>

            <aside class="sk-side">
                <div class="sk-panel">
                    <div class="sk-ringwrap">
                        <svg viewBox="0 0 90 90" class="sk-ring" aria-hidden="true">
                            <circle cx="45" cy="45" r="36" class="track"/>
                            <circle cx="45" cy="45" r="36" class="bar" id="skRing" stroke-dasharray="226.2" stroke-dashoffset="226.2"/>
                        </svg>
                        <b id="skPct">0%</b>
                    </div>
                    <div>
                        <h3>Article strength</h3>
                        <p id="skMsg">Start typing and watch it grow.</p>
                    </div>
                    <ul class="sk-check">
                        <?php foreach (['title' => 'Clear title', 'category' => 'Category', 'problem' => 'Problem described', 'cause' => 'Root cause', 'solution' => 'Solution', 'steps' => 'Steps', 'best' => 'Best practice', 'tags' => 'Tags'] as $k => $l): ?>
                            <li data-check="<?= $k ?>"><i class="fas fa-circle"></i> <?= $l ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="sk-panel sk-preview">
                    <h3><i class="fas fa-eye"></i> Live preview</h3>
                    <span class="sk-pv-cat" id="pvCat">Uncategorized</span>
                    <h4 id="pvTitle">Your title appears here</h4>
                    <p class="lbl">Problem</p><p id="pvProblem" class="ex">…</p>
                    <p class="lbl">Solution</p><p id="pvSolution" class="ex">…</p>
                    <div class="sk-pv-tags" id="pvTags"></div>
                </div>

                <div class="sk-panel sk-tip">
                    <h3><i class="fas fa-wand-magic-sparkles"></i> Writing tip</h3>
                    <p id="skTip">Write for a teammate who has never seen this problem.</p>
                </div>
            </aside>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>