<?php
$plans = [
    [
        "title" => "Academic",
        // "price" => "₱199",
        "features" => ["Feature 1", "Feature 2", "Feature 3"],
        "recommended" => false
    ],
    [
        "title" => "Socio-Civic",
        // "price" => "₱399",
        "features" => ["Feature 1", "Feature 2", "Feature 3"],
        "recommended" => true
    ],
    [
        "title" => "Religious",
        // "price" => "₱599",
        "features" => ["Feature 1", "Feature 2", "Feature 3"],
        "recommended" => false
    ],
    [
        "title" => "Fraternities and Sororities",
        // "price" => "₱999",
        "features" => ["Feature 1", "Feature 2", "Feature 3"],
        "recommended" => false
    ],
    [
        "title" => "Special Interest",
        // "price" => "₱999",
        "features" => ["Feature 1", "Feature 2", "Feature 3"],
        "recommended" => false
    ],
    [
        "title" => "University-Sanctioned",
        // "price" => "₱999",
        "features" => ["Feature 1", "Feature 2", "Feature 3"],
        "recommended" => false
    ],
    [
        "title" => "Student Government",
        // "price" => "₱999",
        "features" => ["Feature 1", "Feature 2", "Feature 3"],
        "recommended" => false
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pricing Plans</title>

<style>
body {
    font-family: 'Segoe UI', sans-serif;
    margin: 0;
    background: linear-gradient(135deg, #eef2f7, #dfe9f3);
}

/* CONTAINER */
.container {
    max-width: 1100px;
    margin: auto;
    padding: 40px 20px;
}

/* VIDEO BOX */
.video-box {
    width: 100%;
    height: 250px;
    background: #e0e0e0;
    border-radius: 20px;
    margin-bottom: 40px;
}

/* CARDS */
.cards {
    display: flex;
    gap: 20px;

    /* ✅ FIXED: prevents cards from moving */
    align-items: flex-start;
}

/* CARD */
.card {
    flex: 1;
    background: linear-gradient(180deg, #4caf50 , #66bb6a);
    border-radius: 25px;
    padding: 30px;
    text-align: center;
    cursor: pointer;

    /* ✅ keeps size stable */
    min-height: 120px;

    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center ;

    box-shadow: 0 8px 25px rgba(0,0,0,0.08);

    /* smooth animation */
    transition: transform 0.35s ease, background 0.3s ease;
}

.card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

/* ONLY ACTIVE MOVES */
.card.active {
    transform: translateY(-25px);
    background: white ;
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

/* HIDDEN CONTENT */
.card-content {
    opacity: 0;
    max-height: 0;
    overflow: hidden;

    transition: all 0.4s ease;
}

/* SHOW CONTENT SMOOTHLY */
.card.active .card-content {
    opacity: 1;
    max-height: 200px;
}

/* BUTTON */
button {
     margin-top: 15px;
    padding: 10px 18px;
    border: none;
    border-radius: 12px;

    background: linear-gradient(135deg, #4caf50, #66bb6a);
    color: white;

    font-weight: bold;
    cursor: pointer;

    transition: 0.3s;
}
    @media (max-width: 900px) {
    .cards {
        flex-direction: column;
    }
}
</style>


</head>

<body>

<div class="container">

    <div class="video-box"></div>

    <div class="cards">

        <?php foreach ($plans as $plan): ?>

            <div class="card <?php echo $plan['recommended'] ? 'active' : ''; ?>" 
                 onclick="selectCard(this)">

                <?php if ($plan['recommended']): ?>
                    <div class="badge">Recommended</div>
                <?php endif; ?>

                <h3><?php echo $plan["title"]; ?></h3>

                <!-- <div class="price">
                    <//?php echo $plan["price"]; ?>
                </div> -->

                <div class="card-content">

                    <?php foreach ($plan["features"] as $feature): ?>
                        <p>✔ <?php echo $feature; ?></p>
                    <?php endforeach; ?>

                    <button onclick="event.stopPropagation(); window.location.href='signup.php?plan=<?php echo $plan['title']; ?>'">
                        Join Now
                    </button>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</div>

<script>
function selectCard(card) {
    document.querySelectorAll('.card').forEach(c =>
        c.classList.remove('active')
    );
    card.classList.add('active');
}
</script>

</body>
</html>