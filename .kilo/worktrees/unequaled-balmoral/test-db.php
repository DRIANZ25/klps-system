<?php
require_once __DIR__ . '/config/config.php';

echo "<h1>Database Debug</h1>";
echo "Environment: " . (getenv('RAILWAY_ENVIRONMENT') ? 'Railway' : 'Local') . "<br>";
echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";
echo "DB_USER: " . DB_USER . "<br><br>";

try {
    $pdo = db();
    echo "✅ Connected successfully!<br><br>";
    
    // Show all databases
    echo "<h3>All Databases:</h3>";
    $stmt = $pdo->query("SHOW DATABASES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "📁 " . $row[0] . "<br>";
    }
    echo "<br>";
    
    // Check if users table exists in klps
    $stmt = $pdo->query("SELECT DATABASE() as current_db");
    $row = $stmt->fetch();
    echo "Current database: " . $row['current_db'] . "<br><br>";
    
    // Try to use the database
    $pdo->exec("USE klps");
    echo "✅ Switched to klps database<br>";
    
    // Show tables in klps
    echo "<h3>Tables in klps:</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "📄 " . $row[0] . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Error Code: " . $e->getCode() . "<br>";
}
?>