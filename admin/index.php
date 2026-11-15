<?php
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
require __DIR__ . '/includes/dev_banner.php';
?>

<main class="container my-5">

    <h1>Find Scholarships</h1>

    <p>
        Welcome to Pathways to Scholarships. Our website helps students and
        families find scholarship opportunities in one place.
    </p>

    <!-- Homepage Quick Search -->
    <section class="card shadow-sm mb-4">

        <div class="card-body">

            <h2 class="h4">Quick Scholarship Search</h2>

            <p>
                Enter a keyword to find scholarships that match
                your interests or educational goals.
            </p>

            <form action="scholarships.php" method="GET">

                <div class="row g-3">

                    <div class="col-md-9">

                        <label for="quick-search" class="form-label">
                            Search Scholarships
                        </label>

                        <input
                            type="search"
                            id="quick-search"
                            name="q"
                            class="form-control"
                            placeholder="e.g. Engineering, STEM, Nursing"
                            maxlength="120"
                            required
                        >

                    </div>

                    <div class="col-md-3 d-flex align-items-end">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Search
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </section>

    <!-- Browse Scholarships -->
    <h2>Browse Scholarships</h2>

    <p>
        Explore available scholarships and review their eligibility requirements,
        award information, and application deadlines.
    </p>

    <p>
        <a class="btn btn-primary" href="scholarships.php">
            Browse Scholarships
        </a>
    </p>

    <!-- Scholarship Information -->
    <h2>Find Scholarships That Fit Your Needs</h2>

    <p>
        Scholarship opportunities may have different requirements based on grade
        level, state, and interests. Review each scholarship's details to see
        whether it is a good match for you.
    </p>

    <!-- Additional Information -->
    <h2>Learn More</h2>

    <p>
        Visit our <a href="about.php">About</a> page to learn about the website,
        read the <a href="faq.php">Frequently Asked Questions</a>, or
        <a href="contact.php">contact us</a> if you need help.
    </p>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
