<?php

$conn = new mysqli("localhost", "root", "", "product_db");


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$conn->query("
    CREATE TABLE IF NOT EXISTS categories (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(255) NOT NULL
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS products (
        product_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        category_id INT,
        FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
    )
");


if (isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    if (!empty($category_name)) {
        $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
        $stmt->bind_param("s", $category_name);
        $stmt->execute();
        $stmt->close();
    }
}


if (isset($_POST['create'])) {
    $name = trim($_POST['name']);
    $price = (float) $_POST['price'];
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : NULL;

    if (!empty($name) && $price >= 0) {
        $stmt = $conn->prepare("INSERT INTO products (name, price, category_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sdi", $name, $price, $category_id);
        $stmt->execute();
        $stmt->close();
    }
}


if (isset($_POST['update']) && isset($_POST['update_id'])) {
    $id = (int) $_POST['update_id'];
    $name = trim($_POST['name']);
    $price = (float) $_POST['price'];
    $category_id = (int) $_POST['category_id'];

    $stmt = $conn->prepare("UPDATE products SET name=?, price=?, category_id=? WHERE product_id=?");
    $stmt->bind_param("sdii", $name, $price, $category_id, $id);
    $stmt->execute();
    $stmt->close();
}

if (isset($_POST['delete']) && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

$products = $conn->query("
    SELECT p.product_id, p.name, p.price, c.category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_id ASC
");

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");


$rankedProducts = $conn->query("
    SELECT 
        p.name,
        p.price,
        c.category_name,
        (
            SELECT COUNT(DISTINCT p2.price) + 1 
            FROM products p2 
            WHERE p2.price > p.price
        ) AS rank
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.price DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Rencel's Product Dashboard</title>
  <style>
    body {font-family: Arial, sans-serif; background-color: #121212; color: #e0e0e0; margin: 0; padding: 20px;}
    .container {max-width: 900px; margin: auto; background: #1e1e1e; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.6);}
    h1, h2 {text-align: center; color: #00bcd4;}
    form {background: #2c2c2c; padding: 15px; border-radius: 8px; margin-bottom: 20px;}
    label {display: block; font-weight: bold; margin-top: 10px;}
    input[type="text"], input[type="number"], select {width: 100%; padding: 8px; border: 1px solid #444; border-radius: 5px; background-color: #1a1a1a; color: #eee;}
    table {width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 15px;}
    th, td {border: 1px solid #333; padding: 10px; text-align: center;}
    th {background-color: #2c2c2c; color: #00bcd4;}
    tr:nth-child(even) {background-color: #1a1a1a;}
    .btn {padding: 8px 15px; border: none; border-radius: 5px; color: white; cursor: pointer; transition: 0.3s;}
    .create-btn {background-color: #00bcd4;}
    .delete-btn {background-color: #f44336;}
    .update-btn {background-color: #00e676;}
    .add-cat-btn {background-color: #ff9800; margin-top: 10px;}
  </style>
</head>
<body>

<div class="container">
  <h1>🛍️ Rencel's Product Dashboard</h1>


  <h2>Add New Category</h2>
  <form method="post">
    <label>Category Name:</label>
    <input type="text" name="category_name" required>
    <button type="submit" name="add_category" class="btn add-cat-btn">Add Category</button>
  </form>

   
  <h2 id="form-title">Add New Product</h2>
  <form method="post" id="productForm">
    <input type="hidden" name="update_id" id="update_id">

    <label>Name:</label>
    <input type="text" name="name" id="name" required>

    <label>Price:</label>
    <input type="number" step="0.01" name="price" id="price" required>

    <label>Category:</label>
    <select name="category_id" id="category_id" required>
      <option value="">-- Select Category --</option>
      <?php
      $cat_result = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
      while ($cat = $cat_result->fetch_assoc()) {
          echo "<option value='{$cat['category_id']}'>{$cat['category_name']}</option>";
      }
      ?>
    </select>

    <button type="submit" name="create" class="btn create-btn" id="createBtn">Create</button>
    <button type="submit" name="update" class="btn update-btn" id="updateBtn" style="display:none;">Update</button>
  </form>

  
  <h2>All Products</h2>
  <table>
    <tr><th>ID</th><th>Name</th><th>Price</th><th>Category</th><th>Actions</th></tr>
    <?php while ($row = $products->fetch_assoc()): ?>
      <tr>
        <td><?= $row['product_id'] ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= number_format($row['price'], 2) ?></td>
        <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
        <td>
          
          <button type="button" class="btn update-btn" 
            onclick="editProduct(
              '<?= $row['product_id'] ?>', 
              '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>', 
              '<?= $row['price'] ?>', 
              '<?= $row['category_name'] ?>'
            )">Edit</button>

        
          <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?');">
            <input type="hidden" name="delete_id" value="<?= $row['product_id'] ?>">
            <button type="submit" name="delete" class="btn delete-btn">Delete</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>

  
  <h2>🏆 Products Ranked by Price (High → Low)</h2>
  <table>
    <tr><th>Rank</th><th>Name</th><th>Price</th><th>Category</th></tr>
    <?php while ($rank = $rankedProducts->fetch_assoc()): ?>
      <tr>
        <td><?= $rank['rank'] ?></td>
        <td><?= htmlspecialchars($rank['name']) ?></td>
        <td><?= number_format($rank['price'], 2) ?></td>
        <td><?= htmlspecialchars($rank['category_name'] ?? 'Uncategorized') ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<script>
function editProduct(id, name, price, categoryName) {
    document.getElementById("update_id").value = id;
    document.getElementById("name").value = name;
    document.getElementById("price").value = price;

    const select = document.getElementById("category_id");
    for (let opt of select.options) {
        if (opt.text === categoryName) {
            opt.selected = true;
            break;
        }
    }

    document.getElementById("form-title").textContent = "Update Product";
    document.getElementById("createBtn").style.display = "none";
    document.getElementById("updateBtn").style.display = "inline";
}
</script>


</body>
</html>
