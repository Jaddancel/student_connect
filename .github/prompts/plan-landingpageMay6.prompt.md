## Plan: Landing Page Wiring + Post Images

Hook the landing page into Vite-loaded assets, replace static sections with the existing LandingPage controller data, and add post image support with factory rules (10 posts per org, every 4th has an image from public/images/sample-images, 2 featured within the 10).

**Steps**

1. Update the asset pipeline to include landing page assets: add resources/css/landingPage.css and resources/js/scripts.js to Vite inputs, then switch the landing page Blade to @vite for those entries and remove the hard-coded css/styles.css + js/scripts.js references. Keep the Bootstrap JS CDN script (used by the navbar/ScrollSpy) and update resources/js/scripts.js to safely access window.bootstrap in module scope.
2. Refactor landing page navigation to use $organizationTypes and $organizationsByType (similar to the organization feed nav), linking each organization to the organization-feed route with its slug. Ensure empty types are skipped.
3. Replace the Organization Clusters section with a loop over organizationTypes, using a small in-Blade map for icon + description text per type key so the cards stay consistent with existing styling.
4. Replace the Featured Organizations grid with a loop over $topFeed (up to 9 orgs), showing logo_url (fallback to initials), announcement_title/excerpt/tag, and a link to the organization feed.
5. Add a Featured Posts section using $featuredPosts and $organizationNameMap; show tag, title, excerpt, org name, date, and render an image only if image_path is present.
6. Add a new migration to add posts.image_path (string, nullable) and update App\Models\Post fillable to include image_path.
7. Update PostFactory to include image_path (default null) and a helper to select a random image from public/images/sample-images.
8. Update OrganizationFactory post seeding to create 10 posts per org, set 2 as featured within those 10, and set image_path on every 4th post (e.g., indices 3 and 7) using a sequence or post-create update.
9. Update the organization feed view to render post images when image_path is present (matching the landing page card style).

**Relevant files**

- `/home/jaddancel/Documents/Creations/student_connect/student_connect/so-connect/vite.config.js` — add landing page inputs
- `/home/jaddancel/Documents/Creations/student_connect/student_connect/so-connect/resources/js/scripts.js` — adapt for Vite module + bootstrap access
- `/home/jaddancel/Documents/Creations/student_connect/student_connect/so-connect/resources/css/landingPage.css` — optional styling tweaks for new sections
- `/home/jaddancel/Documents/Creations/student_connect/student_connect/so-connect/resources/views/landingPage/landingpage.blade.php` — render dynamic nav, clusters, featured orgs, featured posts, and Vite assets
- `/home/jaddancel/Documents/Creations/student_connect/student_connect/so-connect/resources/views/landingPage/organization.blade.php` — show post image when available
- `/home/jaddancel/Documents/Creations/student_connect/student_connect/so-connect/app/Models/Post.php` — add image_path fillable
- `/home/jaddancel/Documents/Creations/student_connect/student_connect/so-connect/database/migrations/*_add_image_path_to_posts_table.php` — new migration for image_path
- `/home/jaddancel/Documents/Creations/student_connect/student_connect/so-connect/database/factories/PostFactory.php` — image_path + helper
- `/home/jaddancel/Documents/Creations/student_connect/student_connect/so-connect/database/factories/OrganizationFactory.php` — 10-post rule, featured selection, every-4th image

**Verification**

1. Run `php artisan migrate:fresh --seed` to verify the new column and post seeding rules (matches repo note about sqlite driver issues).
2. Run `npm run dev` or `npm run build` and load `/` to confirm landing page CSS/JS load correctly (navbar shrink + scrollspy) and dynamic sections render.
3. Visit a few organization feeds to confirm images appear on every 4th post and featured posts show images on the landing page.

**Decisions**

- Use Vite entries for landing page assets.
- Landing page dynamic sections: nav dropdowns, organization clusters, featured organizations, and featured posts.
- Post images stored in posts.image_path, sourced from public/images/sample-images.
- 10 posts per organization, 2 featured within those 10, every 4th has an image.
