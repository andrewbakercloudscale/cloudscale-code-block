<?php
/**
 * One-off prefix migration: cs_devtools_ → csdt_devtools_
 *
 * Run via WP-CLI from the WordPress root:
 *   wp eval-file wp-content/plugins/cloudscale-devtools/migrate-prefix-csdt.php
 *
 * Safe to run multiple times — skips keys that already exist under the new name.
 * Delete this file after running it.
 *
 * NOT part of the plugin product — one-off migration only.
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Allow running via WP-CLI eval-file (which loads WP before executing).
    echo "Run via: wp eval-file path/to/migrate-prefix-csdt.php\n";
    exit( 1 );
}

global $wpdb;

$migrated = 0;
$skipped  = 0;
$errors   = 0;

// ── 1. wp_options ─────────────────────────────────────────────────────────────

echo "=== wp_options ===\n";

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$old_options = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options}
     WHERE option_name LIKE 'cs\_devtools\_%'
     ORDER BY option_name"
);
// phpcs:enable

foreach ( $old_options as $old_key ) {
    $new_key = preg_replace( '/^cs_devtools_/', 'csdt_devtools_', $old_key );
    if ( $new_key === $old_key ) {
        continue;
    }

    if ( get_option( $new_key ) !== false ) {
        echo "  SKIP (already exists): $old_key → $new_key\n";
        $skipped++;
        continue;
    }

    $value = get_option( $old_key );
    $result = add_option( $new_key, $value, '', 'no' );

    if ( $result ) {
        delete_option( $old_key );
        echo "  OK: $old_key → $new_key\n";
        $migrated++;
    } else {
        echo "  ERROR: could not create $new_key\n";
        $errors++;
    }
}

// ── 2. wp_usermeta ────────────────────────────────────────────────────────────

echo "\n=== wp_usermeta ===\n";

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$old_meta_keys = $wpdb->get_col(
    "SELECT DISTINCT meta_key FROM {$wpdb->usermeta}
     WHERE meta_key LIKE 'cs\_devtools\_%'
     ORDER BY meta_key"
);
// phpcs:enable

foreach ( $old_meta_keys as $old_key ) {
    $new_key = preg_replace( '/^cs_devtools_/', 'csdt_devtools_', $old_key );
    if ( $new_key === $old_key ) {
        continue;
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
            $old_key
        )
    );
    // phpcs:enable

    foreach ( $rows as $row ) {
        $existing = get_user_meta( $row->user_id, $new_key, true );
        if ( $existing !== '' && $existing !== false ) {
            echo "  SKIP user {$row->user_id} (already exists): $old_key → $new_key\n";
            $skipped++;
            continue;
        }

        $value = maybe_unserialize( $row->meta_value );
        update_user_meta( $row->user_id, $new_key, $value );
        delete_user_meta( $row->user_id, $old_key );
        echo "  OK user {$row->user_id}: $old_key → $new_key\n";
        $migrated++;
    }
}

// ── 3. wp_postmeta (_cs_social_ → _csdt_social_) ─────────────────────────────

echo "\n=== wp_postmeta ===\n";

$post_meta_map = [
    '_cs_social_formats'        => '_csdt_social_formats',
    '_cs_social_formats_thumb_id' => '_csdt_social_formats_thumb_id',
];

foreach ( $post_meta_map as $old_key => $new_key ) {
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $old_key
        )
    );
    // phpcs:enable

    if ( empty( $rows ) ) {
        echo "  NONE found: $old_key\n";
        continue;
    }

    foreach ( $rows as $row ) {
        $existing = get_post_meta( $row->post_id, $new_key, true );
        if ( $existing !== '' && $existing !== false ) {
            echo "  SKIP post {$row->post_id} (already exists): $old_key → $new_key\n";
            $skipped++;
            continue;
        }

        $value = maybe_unserialize( $row->meta_value );
        update_post_meta( $row->post_id, $new_key, $value );
        delete_post_meta( $row->post_id, $old_key );
        echo "  OK post {$row->post_id}: $old_key → $new_key\n";
        $migrated++;
    }
}

// ── 4. Transients (cs_devtools_ prefix) ──────────────────────────────────────

echo "\n=== transients ===\n";

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$transient_rows = $wpdb->get_results(
    "SELECT option_name, option_value FROM {$wpdb->options}
     WHERE option_name LIKE '\_transient\_cs\_devtools\_%'
        OR option_name LIKE '\_transient\_timeout\_cs\_devtools\_%'"
);
// phpcs:enable

foreach ( $transient_rows as $row ) {
    $old_name = $row->option_name;
    $new_name = str_replace( '_transient_cs_devtools_', '_transient_csdt_devtools_', $old_name );
    $new_name = str_replace( '_transient_timeout_cs_devtools_', '_transient_timeout_csdt_devtools_', $new_name );

    if ( $new_name === $old_name ) {
        continue;
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $result = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_name = %s WHERE option_name = %s",
            $new_name,
            $old_name
        )
    );
    // phpcs:enable

    if ( $result ) {
        echo "  OK: $old_name → $new_name\n";
        $migrated++;
    } else {
        echo "  SKIP/ERROR: $old_name\n";
        $skipped++;
    }
}

// ── Summary ───────────────────────────────────────────────────────────────────

echo "\n=== Done ===\n";
echo "Migrated : $migrated\n";
echo "Skipped  : $skipped\n";
echo "Errors   : $errors\n";
echo "\nIMPORTANT: Delete this file after running it.\n";
