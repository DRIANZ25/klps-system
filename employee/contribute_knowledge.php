<?php
$page_title = 'Contribute to Knowledge Library';
$page_css = ['employee-contribute_knowledge.css'];
$page_js  = ['employee-contribute_knowledge.js'];
require_once __DIR__ . '/../partials/header.php';

$currentUserId = id_param($_SESSION['user']['users_id'] ?? '');
$conversationId = id_param($_GET['conversation_id'] ?? $_POST['conversation_id'] ?? '');

if ($conversationId === '') redirect('chat_history.php');

$stmt = db()->prepare('SELECT * FROM ai_conversations WHERE ai_conversations_id = ? AND user_id = ?');
$stmt->execute([$conversationId, $currentUserId]);
$conversation = $stmt->fetch();
if (!$conversation) redirect('chat_history.php');

try {
    $stmt = db()->prepare('SELECT contributed_knowledge_id FROM ai_conversations WHERE ai_conversations_id = ?');
    $stmt->execute([$conversationId]);
    $existingId = $stmt->fetchColumn();
    if ($existingId) redirect('knowledge_view.php?id=' . $existingId);
} catch (PDOException $e) {}

$stmt = db()->prepare('SELECT * FROM ai_messages WHERE conversation_id = ? ORDER BY created_at ASC, CAST(SUBSTRING(ai_messages_id, 2) AS UNSIGNED) ASC');
$stmt->execute([$conversationId]);
$messages = $stmt->fetchAll();

if (empty($messages)) redirect('ai_chat.php?conversation_id=' . $conversationId);

$categories = [];
$departments = [];
try {
    $categories = db()->query('SELECT knowledge_categories_id AS id, name FROM knowledge_categories ORDER BY name')->fetchAll();
} catch (PDOException $e) {}
try {
    $departments = db()->query('SELECT departments_id AS id, name FROM departments ORDER BY name')->fetchAll();
} catch (PDOException $e) {}

$currentUserDept = '';
try {
    $s = db()->prepare('SELECT department_id FROM users WHERE users_id = ?');
    $s->execute([$currentUserId]);
    $currentUserDept = id_param($s->fetchColumn());
} catch (PDOException $e) {}

/* ============================================================
   HELPERS
   ============================================================ */

/**
 * Strips markdown syntax from a string and optionally caps its length.
 */
function klps_strip_md(string $text, int $max = 0): string {
    $t = trim($text);
    $t = preg_replace('/^#{1,6}\s*/m', '', $t);
    $t = preg_replace('/\*\*(.+?)\*\*/', '$1', $t);
    $t = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '$1', $t);
    $t = preg_replace('/__(.+?)__/', '$1', $t);
    $t = preg_replace('/`(.+?)`/', '$1', $t);
    $t = preg_replace('/\[(.+?)\]\(.+?\)/', '$1', $t);
    $t = preg_replace('/^-{3,}$/m', '', $t);
    $t = preg_replace('/^[\-\*\+]\s+/m', '', $t);
    $t = str_replace(['→', '->', '=>', '⇒'], '>', $t);
    $t = preg_replace('/\$\s*\\\\?(?:rightarrow|to|Rightarrow)\s*\$/i', '>', $t);
    $t = preg_replace("/\n{3,}/", "\n\n", $t);
    $t = trim($t);
    if ($max > 0 && mb_strlen($t) > $max) {
        $t = mb_substr($t, 0, $max - 3) . '...';
    }
    return $t;
}

/**
 * Extracts a real title from arbitrary text.
 * If it's a paragraph, takes the first line or first sentence.
 */
function klps_extract_title(string $raw, string $fallback = 'Untitled Article', int $max = 90): string {
    $t = klps_strip_md($raw);

    if (strpos($t, "\n") !== false) {
        foreach (preg_split('/\r?\n/', $t) as $line) {
            $line = trim($line);
            if ($line !== '' && mb_strlen($line) >= 8) { $t = $line; break; }
        }
    }

    if (mb_strlen($t) > $max) {
        if (preg_match('/^(.+?[\.\?\!])\s/', $t, $m)) {
            $candidate = trim($m[1]);
            $t = (mb_strlen($candidate) >= 8 && mb_strlen($candidate) <= $max)
                ? $candidate
                : mb_substr($t, 0, $max - 3) . '...';
        } else {
            $t = mb_substr($t, 0, $max - 3) . '...';
        }
    }

    $t = trim($t);
    return $t !== '' ? $t : $fallback;
}

/**
 * Cleans procedure_steps so it renders as a clean numbered list.
 * - Strips markdown
 * - Folds stray labels like "Phase 1:" away
 * - Re-numbers everything 1..N
 */
function klps_clean_steps(string $raw): string {
    $t = klps_strip_md($raw);
    $t = str_replace(['→', '->', '=>', '⇒'], '>', $t);
    $t = preg_replace('/\$\s*\\\\?(?:rightarrow|to|Rightarrow)\s*\$/i', '>', $t);

    $lines = preg_split('/\r?\n/', $t);
    $out = [];
    $n = 1;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        $line = preg_replace('/^\d+[\.\)]\s*/', '', $line);
        $line = preg_replace('/^[\-\*•]\s*/', '', $line);

        if (preg_match('/^(phase|step|part)\s*[\dA-Z]+\s*:?\s*$/i', $line)) continue;

        $line = rtrim($line, ';:');
        if ($line === '') continue;

        $out[] = $n . '. ' . $line;
        $n++;
    }
    return implode("\n", $out);
}

function klps_category_icon(?string $name): string {
    $name = strtolower($name ?? '');
    $map = [
        'network' => 'fa-network-wired', 'security' => 'fa-shield-halved', 'database' => 'fa-database',
        'hardware' => 'fa-microchip', 'software' => 'fa-code', 'cloud' => 'fa-cloud',
        'email' => 'fa-envelope', 'printer' => 'fa-print', 'server' => 'fa-server',
        'mobile' => 'fa-mobile-screen', 'browser' => 'fa-globe', 'account' => 'fa-user-lock',
        'vpn' => 'fa-lock', 'backup' => 'fa-clock-rotate-left', 'wi-fi' => 'fa-wifi', 'wifi' => 'fa-wifi',
    ];
    foreach ($map as $key => $icon) {
        if (str_contains($name, $key)) return $icon;
    }
    return 'fa-lightbulb';
}

function buildTranscript(array $messages): string {
    $lines = [];
    foreach ($messages as $m) {
        $speaker = $m['sender'] === 'user' ? 'User' : 'AI';
        $lines[] = $speaker . ': ' . $m['message'];
    }
    return implode("\n\n", $lines);
}

function draftFromTranscriptHeuristic(array $messages): array {
    $firstUserMsg = '';
    $aiTexts = [];
    foreach ($messages as $m) {
        if ($m['sender'] === 'user' && $firstUserMsg === '') $firstUserMsg = $m['message'];
        if ($m['sender'] === 'ai') $aiTexts[] = $m['message'];
    }
    $solutionRaw = implode("\n\n", $aiTexts);

    return [
        'title' => klps_extract_title($firstUserMsg, 'Untitled Problem', 90),
        'problem' => klps_strip_md($firstUserMsg, 2000),
        'cause' => '',
        'solution' => klps_strip_md($solutionRaw, 6000),
        'procedure_steps' => '',
        'best_practice' => '',
        'tags' => '',
        'category_id' => '',
        'department_id' => '',
    ];
}

function klps_detect_category_id(array $messages, array $categories): string {
    $bag = '';
    foreach ($messages as $m) {
        if ($m['sender'] === 'user') $bag .= ' ' . strtolower($m['message']);
    }

    $rules = [
        'wi-fi'    => ['wi-fi','wifi','wireless','wlan','router'],
        'network'  => ['network','vpn','ethernet','dns','ip address','dhcp','latency'],
        'printer'  => ['printer','printing','ink','toner','paper'],
        'security' => ['phishing','malware','virus','firewall','security','hacked','password','breach'],
        'hardware' => ['battery','keyboard','mouse','monitor','usb','hardware','charger','screen',
                       'laptop','desktop','computer','pc','performance','fan','overheating'],
        'software' => ['excel','word','outlook','app','software','browser','chrome','firefox','update'],
        'cloud'    => ['cloud','backup','storage','onedrive','dropbox','drive'],
        'server'   => ['server','disk','cpu','memory','ram'],
        'database' => ['database','sql','mysql','query','table'],
    ];

    foreach ($rules as $keyword => $terms) {
        foreach ($terms as $term) {
            if (strpos($bag, $term) !== false) {
                foreach ($categories as $c) {
                    if (stripos($c['name'], $keyword) !== false) return $c['id'];
                }
            }
        }
    }
    return '';
}

function klps_detect_tags(array $messages): string {
    $bag = '';
    foreach ($messages as $m) {
        if ($m['sender'] === 'user') $bag .= ' ' . strtolower($m['message']);
    }
    $tags = [];
    foreach (['wifi','vpn','printer','excel','windows','mac','security','network','slow','battery'] as $kw) {
        if (strpos($bag, $kw) !== false) $tags[] = $kw;
    }
    return implode(', ', array_slice($tags, 0, 5));
}

/**
 * Sends a raw prompt to the configured AI provider and returns its plain-text
 * reply, or null on any failure.
 *
 * AI_API_URL points at Groq's OpenAI-compatible endpoint, so this must use the
 * OpenAI wire format: the key goes in an Authorization: Bearer header and the
 * body carries model + messages. The previous version appended the key as a
 * "?key=" query parameter and sent Gemini's {"contents":[{"parts":[...]}]}
 * body, which Groq rejects with HTTP 401, so the structured draft silently
 * fell back to the bare heuristic and left cause/steps/best_practice empty.
 */
function callAIRaw(string $prompt, int $maxTokens = 4000): ?string {
    $payload = [
        'model' => AI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert IT support knowledge-base editor. '
                    . 'Follow the requested output format exactly and output nothing else.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'temperature' => 0.3,
        'max_tokens'  => $maxTokens,
    ];

    $ch = curl_init(AI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . AI_API_KEY,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('AI draft curl error: ' . $curlError);
        return null;
    }
    if ($httpCode !== 200) {
        error_log('AI draft HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 500));
        return null;
    }

    $result = json_decode((string)$response, true);
    $text = $result['choices'][0]['message']['content'] ?? null;
    return (is_string($text) && trim($text) !== '') ? $text : null;
}

function aiStructureConversation(string $transcript): ?array {
    $prompt = "You are converting a technical support chat log into a knowledge-base article. "
        . "Read the conversation and respond ONLY in this exact plain-text format — no markdown, no code fences, no extra commentary.\n\n"

        . "STRICT RULES (read carefully):\n"
        . "1. TITLE: ONE LINE, under 80 characters, a short summary of the problem. Example: 'Excel Freezes When Opening Large Files'. Never paste steps or explanations into the title.\n"
        . "2. PROBLEM: 1-2 sentences. Just what the user complained about. No solution content here.\n"
        . "3. CAUSE: 1 short sentence describing why this happens. If not discussed, write the word Unknown.\n"
        . "4. SOLUTION: 2-4 sentences giving an OVERVIEW of the fix. Do NOT paste the full procedure here — the detailed procedure goes in STEPS. Just summarize the main idea.\n"
        . "5. STEPS: The detailed procedure as a flat numbered list, ONE short step per line. Rules:\n"
        . "   - Each step is a single line of plain text.\n"
        . "   - If the conversation used phase labels like 'Phase 1' or 'A.' 'B.' or headings, fold them into the step text instead of making them separate lines.\n"
        . "   - Example format:\n"
        . "     1. Set Excel calculation to Manual: File > Options > Formulas > Manual.\n"
        . "     2. Open the file with File > Open.\n"
        . "     3. For a corrupted file, use File > Open > Browse, select the file, then click the arrow next to Open and choose Open and Repair.\n"
        . "     4. Save the file as Excel Binary Workbook (.xlsb) to reduce its size.\n"
        . "     5. Remove excess formatting by pressing Ctrl+End and clearing unused rows and columns.\n"
        . "   - Aim for 5 to 12 steps. No sub-headings. No bold. No bullets inside steps.\n"
        . "6. BEST_PRACTICE: One short sentence with a preventive tip. Optional.\n"
        . "7. TAGS: 2 to 5 comma-separated keywords.\n\n"

        . "FORMATTING RULES FOR EVERY FIELD:\n"
        . "- Do NOT use markdown symbols like **, ##, ```, ---, or ###.\n"
        . "- Do NOT use special arrow characters like -> or →. Write '>' if you must.\n"
        . "- Do NOT use math symbols like \$\\rightarrow\$.\n"
        . "- Use plain English sentences and simple punctuation.\n"
        . "- Keep each field focused only on what that field is for.\n\n"

        . "Now respond with exactly this structure:\n"
        . "TITLE: ...\n"
        . "PROBLEM: ...\n"
        . "CAUSE: ...\n"
        . "SOLUTION: ...\n"
        . "STEPS:\n1. ...\n2. ...\n3. ...\n"
        . "BEST_PRACTICE: ...\n"
        . "TAGS: ...\n\n"

        . "Conversation:\n" . $transcript;

    $text = callAIRaw($prompt);
    if (!$text) return null;

    // Reasoning models often wrap the answer in code fences or add a preamble.
    $text = trim((string)preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', trim($text)));

    $fieldMap = [
        'TITLE'          => 'title',
        'PROBLEM'        => 'problem',
        'CAUSE'          => 'cause',
        'SOLUTION'       => 'solution',
        'STEPS'          => 'procedure_steps',
        'BEST_PRACTICE'  => 'best_practice',
        'TAGS'           => 'tags',
    ];

    // Slice the text by label offsets rather than preg_split + count equality.
    // That tolerates extra prose around the block, a repeated label inside a
    // value, and both "BEST_PRACTICE:" and "BEST PRACTICE:" spellings.
    $labelRe = '/^[ \t]*(TITLE|PROBLEM|CAUSE|ROOT[_ ]?CAUSE|SOLUTION|STEPS|BEST[_ ]?PRACTICE|TAGS):[ \t]*/mi';
    if (!preg_match_all($labelRe, $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        error_log('AI draft: no field labels found in response');
        return null;
    }

    $labels = [];
    foreach ($m as $set) {
        $key = strtoupper($set[1][0]);
        if ($key === 'ROOT CAUSE' || $key === 'ROOT_CAUSE') $key = 'CAUSE';
        if ($key === 'BEST PRACTICE') $key = 'BEST_PRACTICE';
        $labels[] = [
            'key'   => $key,
            'match' => $set[0][1],
            'value' => $set[0][1] + strlen($set[0][0]),
        ];
    }

    $result = [];
    $last = count($labels) - 1;
    foreach ($labels as $i => $label) {
        if (!isset($fieldMap[$label['key']])) continue;
        $end = ($i < $last) ? $labels[$i + 1]['match'] : strlen($text);
        $value = trim(substr($text, $label['value'], max(0, $end - $label['value'])));
        if (strcasecmp($value, 'Unknown') === 0 && $fieldMap[$label['key']] === 'cause') $value = '';
        $result[$fieldMap[$label['key']]] = $value;
    }

    // Without these three there is no usable article, so treat it as a failure.
    foreach (['title', 'problem', 'solution'] as $required) {
        if (empty($result[$required])) {
            error_log('AI draft: missing required field ' . $required);
            return null;
        }
    }
    return $result;
}

/**
 * Overlays AI-generated fields onto a draft. Only non-empty AI values win, so a
 * missing section keeps the heuristic's value instead of blanking the field.
 */
function applyAiDraftFields(array $draft, ?array $aiResult): array {
    if (!$aiResult) return $draft;

    if (!empty($aiResult['title']))           $draft['title']           = klps_extract_title($aiResult['title'], $draft['title'], 90);
    if (!empty($aiResult['problem']))         $draft['problem']         = klps_strip_md($aiResult['problem'], 2000);
    if (!empty($aiResult['cause']))           $draft['cause']           = klps_strip_md($aiResult['cause'], 1000);
    if (!empty($aiResult['solution']))        $draft['solution']        = klps_strip_md($aiResult['solution'], 6000);
    if (!empty($aiResult['procedure_steps'])) $draft['procedure_steps'] = klps_clean_steps($aiResult['procedure_steps']);
    if (!empty($aiResult['best_practice']))   $draft['best_practice']   = klps_strip_md($aiResult['best_practice'], 800);
    if (!empty($aiResult['tags']))            $draft['tags']            = klps_strip_md($aiResult['tags'], 200);

    return $draft;
}

$action = $_POST['action'] ?? '';
$draft = null;
$aiDraftNote = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'draft_ai') {
    $aiResult = aiStructureConversation(buildTranscript($messages));

    $draft = draftFromTranscriptHeuristic($messages);
    $draft = applyAiDraftFields($draft, $aiResult);

    if ($aiResult) {
        $_SESSION['ck_ai_draft_' . $conversationId] = $aiResult;
        $aiDraftNote = ['type' => 'success', 'text' => '✨ Draft generated by AI — review and edit before publishing.'];
    } else {
        $aiDraftNote = ['type' => 'warning', 'text' => 'Could not reach the AI to structure this — here is a basic draft instead.'];
    }

    if (empty($draft['category_id'])) {
        $draft['category_id'] = klps_detect_category_id($messages, $categories);
    }
    if (empty($draft['tags'])) {
        $draft['tags'] = klps_detect_tags($messages);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'publish') {

    // Sanitize EVERYTHING before saving (user may have pasted markdown)
    $title           = klps_extract_title($_POST['title'] ?? '', 'Untitled Article', 120);
    $category_id     = id_param($_POST['category_id'] ?? '');
    $department_id   = id_param($_POST['department_id'] ?? '');
    $problem         = klps_strip_md($_POST['problem'] ?? '', 3000);
    $cause           = klps_strip_md($_POST['cause'] ?? '', 1500);
    $solution        = klps_strip_md($_POST['solution'] ?? '', 8000);
    $procedure_steps = klps_clean_steps($_POST['procedure_steps'] ?? '');
    $best_practice   = klps_strip_md($_POST['best_practice'] ?? '', 1000);
    $tags            = klps_strip_md($_POST['tags'] ?? '', 200);

    if ($title && $problem && $solution) {
        try {
            $newKnowledgeId = new_id('knowledge');
            $stmt = db()->prepare(
                'INSERT INTO knowledge (knowledge_id, category_id, department_id, created_by, title, problem, cause, solution, procedure_steps, best_practice, tags, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active")'
            );
            $stmt->execute([
                $newKnowledgeId,
                $category_id ?: null,
                $department_id ?: ($currentUserDept ?: null),
                $currentUserId,
                $title, $problem, $cause, $solution, $procedure_steps, $best_practice, $tags
            ]);

            try {
                $stmt = db()->prepare('UPDATE ai_conversations SET contributed_knowledge_id = ? WHERE ai_conversations_id = ?');
                $stmt->execute([$newKnowledgeId, $conversationId]);
            } catch (PDOException $e) {}

            $_SESSION['success'] = '📚 Your conversation is now part of the Knowledge Library!';
            redirect('knowledge_view.php?id=' . $newKnowledgeId);
        } catch (PDOException $e) {
            error_log('Knowledge publish failed: ' . $e->getMessage());
            $aiDraftNote = ['type' => 'error', 'text' => 'Failed to publish article: ' . $e->getMessage()];
            $draft = compact('title', 'problem', 'cause', 'solution', 'procedure_steps', 'best_practice', 'tags')
                   + ['category_id' => $category_id, 'department_id' => $department_id];
        }
    } else {
        $aiDraftNote = ['type' => 'error', 'text' => 'Title, Problem, and Solution are required before publishing.'];
        $draft = compact('title', 'problem', 'cause', 'solution', 'procedure_steps', 'best_practice', 'tags')
               + ['category_id' => $category_id, 'department_id' => $department_id];
    }
}

if ($draft === null) {
    $draft = draftFromTranscriptHeuristic($messages);

    // Arriving straight from the AI chat: build the whole article (root cause,
    // procedure steps, best practice, tags) automatically so the user only has
    // to review it. The result is cached in the session, so a page reload reuses
    // it instead of spending another API call.
    $cacheKey = 'ck_ai_draft_' . $conversationId;
    if (!empty($_SESSION[$cacheKey])) {
        $draft = applyAiDraftFields($draft, $_SESSION[$cacheKey]);
        $aiDraftNote = ['type' => 'success', 'text' => '✨ Draft generated by AI — review and edit before publishing.'];
    } else {
        $aiResult = aiStructureConversation(buildTranscript($messages));
        $draft = applyAiDraftFields($draft, $aiResult);
        if ($aiResult) {
            $_SESSION[$cacheKey] = $aiResult;
            $aiDraftNote = ['type' => 'success', 'text' => '✨ Draft generated by AI — review and edit before publishing.'];
        } else {
            $aiDraftNote = ['type' => 'warning', 'text' => 'Could not reach the AI to structure this — here is a basic draft instead.'];
        }
    }

    $draft['category_id'] = $draft['category_id'] ?: klps_detect_category_id($messages, $categories);
    $draft['tags'] = $draft['tags'] ?: klps_detect_tags($messages);
}
$draft += ['category_id' => '', 'department_id' => ''];
// Pre-select the author's own department so the field is never left blank on publish.
if (($draft['department_id'] ?? '') === '') $draft['department_id'] = $currentUserDept;
?>


<div class="ck-page">
    <div class="ck-scenery" id="ckScenery"></div>

    <div class="ck-header">
        <h1><span class="logo-badge"><i class="fas fa-book-open"></i></span> Contribute to Knowledge Library</h1>
        <p>Turn this AI conversation into an article so others facing the same problem can find the fix.</p>
    </div>

    <?php if ($aiDraftNote): ?>
        <div class="ck-note <?= e($aiDraftNote['type']) ?>">
            <i class="fas fa-<?= $aiDraftNote['type'] === 'success' ? 'wand-magic-sparkles' : ($aiDraftNote['type'] === 'error' ? 'circle-exclamation' : 'triangle-exclamation') ?>"></i>
            <?= e($aiDraftNote['text']) ?>
        </div>
    <?php endif; ?>

    <div class="ck-layout">
        <div class="ck-form-card">
            <div class="ck-form-actions-top">
                <h2><i class="fas fa-pen-to-square me-2"></i>Review the article</h2>
                <form method="post" id="aiDraftForm">
                    <input type="hidden" name="conversation_id" value="<?= $conversationId ?>">
                    <input type="hidden" name="action" value="draft_ai">
                    <button type="submit" class="btn-ai-draft" id="aiDraftBtn">
                        <i class="fas fa-wand-magic-sparkles"></i> Draft with AI
                    </button>
                </form>
            </div>

            <form method="post">
                <input type="hidden" name="conversation_id" value="<?= $conversationId ?>">
                <input type="hidden" name="action" value="publish">

                <div class="form-group">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" value="<?= e($draft['title']) ?>" maxlength="200" required>
                </div>

                <div class="ck-form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">Select</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= e($c['id']) ?>" <?= e($draft['category_id']) === $c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id" class="form-control">
                            <option value="">Select</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= e($d['id']) ?>" <?= e($draft['department_id']) === $d['id'] ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Problem <span class="required">*</span></label>
                    <textarea name="problem" class="form-control" rows="3" required><?= e($draft['problem']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Root Cause</label>
                    <textarea name="cause" class="form-control" rows="2"><?= e($draft['cause']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Solution <span class="required">*</span></label>
                    <textarea name="solution" class="form-control" rows="4" required><?= e($draft['solution']) ?></textarea>
                    <small style="color:var(--text-dim);font-size:0.72rem;display:block;margin-top:0.35rem;">
                        <i class="fas fa-info-circle" style="color:var(--cyan);"></i>
                        Keep this as a short summary (2-4 sentences). Put the detailed procedure in Steps below.
                    </small>
                </div>

                <div class="form-group">
                    <label>Procedure Steps <span style="text-transform:none;color:rgba(136,146,176,0.7);font-weight:500;">(one step per line)</span></label>
                    <textarea name="procedure_steps" class="form-control" rows="6" placeholder="1. First step&#10;2. Second step"><?= e($draft['procedure_steps']) ?></textarea>
                    <small style="color:var(--text-dim);font-size:0.72rem;display:block;margin-top:0.35rem;">
                        <i class="fas fa-info-circle" style="color:var(--cyan);"></i>
                        One action per line. Numbers and bullets are added automatically.
                    </small>
                </div>

                <div class="form-group">
                    <label>Best Practice</label>
                    <textarea name="best_practice" class="form-control" rows="2"><?= e($draft['best_practice']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Tags</label>
                    <input type="text" name="tags" class="form-control" value="<?= e($draft['tags']) ?>" placeholder="excel, performance, windows">
                </div>

                <button type="submit" class="btn-publish"><i class="fas fa-book-open"></i> Publish to Knowledge Library</button>
                <a href="ai_chat.php?conversation_id=<?= $conversationId ?>" class="ck-cancel-link">Cancel and go back to the chat</a>
            </form>
        </div>

        <div class="ck-transcript">
            <h6><i class="fas fa-comments"></i> Original Conversation</h6>
            <?php foreach ($messages as $m): ?>
                <div class="ck-msg <?= e($m['sender']) ?>">
                    <div class="ck-avatar">
                        <?php if ($m['sender'] === 'user'): ?>
                            <?php if ($profilePic && file_exists(__DIR__ . '/../uploads/profiles/' . $user['profile_picture'])): ?>
                                <img src="<?= e($profilePic) ?>" alt="<?= e($user['full_name']) ?>">
                            <?php else: ?>
                                <?= e(strtoupper(mb_substr($user['full_name'] ?? '?', 0, 1))) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <i class="fas fa-robot" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <div class="ck-bubble"><?= nl2br(e($m['message'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>



<?php require_once __DIR__ . '/../partials/footer.php'; ?>