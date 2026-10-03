<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .container {
            padding: 20px;
        }
        .email-header {
            background-color: #ffffff;
            padding: 20px;
            text-align: center;
            border-bottom: 2px solid #f4f4f4;
            margin-bottom: 20px;
        }
        .email-logo {
            max-width: 100px;
            height: auto;
        }
        .email-site-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        .header { 
            background-color: #f4f4f4; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 20px; 
        }
        .header h2 {
            margin: 0;
            color: #333;
        }
        .changes-section { 
            margin: 20px 0; 
        }
        .change-item { 
            margin: 15px 0; 
            padding: 10px; 
            background-color: #f9f9f9; 
            border-left: 4px solid #0073aa; 
        }
        .change-label { 
            font-weight: bold; 
            margin-bottom: 5px; 
        }
        .old-value { 
            color: #d63638; 
        }
        .new-value { 
            color: #00a32a; 
        }
        .summary-section { 
            margin-top: 30px; 
            padding: 15px; 
            background-color: #e5f5fa; 
            border-radius: 5px; 
        }
        .summary-item { 
            margin: 8px 0; 
        }
        hr { 
            border: none; 
            border-top: 2px solid #ddd; 
            margin: 20px 0; 
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <?php
        // Obtener logo del sitio
        $logo_id = get_theme_mod( 'custom_logo' );
        $logo_url = '';
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
        }
        // Si no hay logo personalizado, intentar con el site icon
        if ( ! $logo_url ) {
            $logo_url = get_site_icon_url( 512 );
        }
        
        // Obtener nombre del sitio
        $site_name = get_bloginfo( 'name' );
        ?>
        <div class="email-header">
            <?php if ( $logo_url ) : ?>
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" class="email-logo" />
            <?php endif; ?>
            <?php if ( $site_name ) : ?>
                <h1 class="email-site-name"><?php echo esc_html( $site_name ); ?></h1>
            <?php endif; ?>
        </div>
        <div class="container">

