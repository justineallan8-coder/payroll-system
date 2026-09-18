<?php
$page_title = 'Announcements';
require_once __DIR__ . '/includes/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($title === '' || $message === '') {
        $error = 'Please enter both a title and announcement message.';
    } else {
        $safeTitle = mysqli_real_escape_string($conn, $title);
        $safeMessage = mysqli_real_escape_string($conn, $message);
        $createdBy = (int)$_SESSION['user_id'];

        if (mysqli_query($conn, "
            INSERT INTO announcements (created_by, title, message)
            VALUES ($createdBy, '$safeTitle', '$safeMessage')
        ")) {
            $announcementId = mysqli_insert_id($conn);
            $users = mysqli_query($conn, "SELECT id FROM users WHERE status = 'active' AND role <> 'admin'");
            if ($users) {
                while ($user = mysqli_fetch_assoc($users)) {
                    createNotification(
                        (int)$user['id'],
                        'announcement',
                        $title,
                        $message,
                        $announcementId
                    );
                }
            }

            logActivity($createdBy, 'post_announcement', "Posted announcement: $title");
            setFlash('success', 'Announcement posted and sent to all active employees.');
            redirect('announcements.php');
        }

        $error = 'The announcement could not be posted. Please try again.';
    }
}

$announcements = mysqli_query($conn, "
    SELECT a.*, u.full_name AS author_name
    FROM announcements a
    LEFT JOIN users u ON u.id = a.created_by
    ORDER BY a.created_at DESC
    LIMIT 30
");
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">Announcements</h1>
        <p class="text-gray-500 text-sm mt-1">Company updates and important notices</p>
    </div>
</div>

<?php if (isAdmin()): ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex items-center space-x-3 mb-5">
        <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
            <i class="fas fa-bullhorn text-indigo-600"></i>
        </div>
        <div>
            <h2 class="font-bold text-gray-900">Post an announcement</h2>
            <p class="text-sm text-gray-500">Every active employee will receive a notification.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg mb-4">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input id="title" type="text" name="title" required maxlength="255"
                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                   placeholder="e.g. Office closed on Friday"
                   class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message</label>
            <textarea id="message" name="message" required rows="4"
                      placeholder="Write the announcement here..."
                      class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
        </div>
        <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl hover:bg-indigo-700 transition flex items-center space-x-2">
            <i class="fas fa-paper-plane"></i>
            <span>Post Announcement</span>
        </button>
    </form>
</div>
<?php endif; ?>

<div class="space-y-4">
    <?php if ($announcements && mysqli_num_rows($announcements) > 0): ?>
        <?php while ($announcement = mysqli_fetch_assoc($announcements)): ?>
        <article class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-indigo-500">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($announcement['title']); ?></h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Posted by <?php echo htmlspecialchars($announcement['author_name'] ?? 'Administrator'); ?>
                        on <?php echo date('M d, Y \a\t H:i', strtotime($announcement['created_at'])); ?>
                    </p>
                </div>
                <i class="fas fa-bullhorn text-indigo-500 mt-1"></i>
            </div>
            <p class="text-gray-700 mt-4 whitespace-pre-line"><?php echo htmlspecialchars($announcement['message']); ?></p>
        </article>
        <?php endwhile; ?>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-500">
        <i class="fas fa-bullhorn text-3xl text-gray-300 mb-3"></i>
        <p>No announcements have been posted yet.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>