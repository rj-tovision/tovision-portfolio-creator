<?php
/**
 * The template for displaying all single portfolio posts
 */

get_header();

?>

<main id="site-content" role="main">
    <?php
    while ( have_posts() ) :
        the_post();
        
        // Get ACF fields
        $date = get_field('datum');
        if($date) {
            $date_obj = DateTime::createFromFormat('Ymd', $date);
            $formatted_date = $date_obj ? $date_obj->format('j F Y') : $date;
        } else {
            $formatted_date = '';
        }

        $location = get_field('locatie');
        $gallery = get_field('gallerij');
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class('portfolio-single-container'); ?>>
        
        <div class="portfolio-content-wrapper">
            <header class="portfolio-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
                <div class="portfolio-meta">
                    <?php if ( $formatted_date ) : ?>
                        <span class="portfolio-date"><?php echo esc_html( $formatted_date ); ?></span>
                    <?php endif; ?>
                    
                    <?php if ( $location ) : ?>
                        <span class="portfolio-location"><?php echo esc_html( $location ); ?></span>
                    <?php endif; ?>
                </div>
            </header>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>
            
            <a href="javascript:history.back()" class="portfolio-back-link">&larr; Terug naar overzicht</a>
        </div>

        <div class="portfolio-gallery-wrapper">
            <?php if ( $gallery ) : ?>
                <div class="portfolio-grid">
                    <?php foreach ( $gallery as $item ) :
                        // Handle both ID array and Object array from ACF
                        $image_id = is_array($item) ? $item['ID'] : $item;
                        
                        $image_thumb = wp_get_attachment_image_src($image_id, 'medium_large'); // Good for 400px+
                        $image_full = wp_get_attachment_image_src($image_id, 'full');

                        if ( ! $image_thumb ) continue; // Skip if image not found
                    ?>
                        <div class="portfolio-grid-item">
                            <a href="<?php echo esc_url($image_full[0]); ?>" class="portfolio-lightbox-link" data-lightbox="portfolio-gallery">
                                <?php echo wp_get_attachment_image( $image_id, 'medium_large' ); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </article>

    <?php endwhile; ?>
    
    <!-- Lightbox Structure -->
    <div id="portfolio-lightbox" class="portfolio-lightbox">
        <span class="lightbox-close">&times;</span>
        <div class="lightbox-content">
            <img class="lightbox-image" src="" alt="Portfolio Image">
        </div>
        <a class="lightbox-prev">&#10094;</a>
        <a class="lightbox-next">&#10095;</a>
    </div>

</main>

<?php get_footer(); ?>