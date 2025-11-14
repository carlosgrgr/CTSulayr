<?php

namespace TotalThemeCore\Vcex\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Returns image from source.
 */
class Sort_Attachments_by_Exif {

	/**
	 * Sort attachments by EXIF data.
	 *
	 * @param array  $attachment_ids Array of attachment IDs.
	 * @param string $exif_field     EXIF field to sort by (default 'DateTimeOriginal').
	 * @return array Sorted attachment IDs.
	 */
	public static function sort( array $attachment_ids, string $exif_field = 'DateTimeOriginal', string $order = 'DESC' ): array {
		if ( empty( $attachment_ids ) ) {
			return [];
		}

		$attachments = [];
		$date_fields = [ 'DateTimeOriginal', 'CreateDate', 'DateTimeDigitized' ];

		foreach ( $attachment_ids as $id ) {
			$file = get_attached_file( $id );

			if ( ! $file ) {
				continue;
			}

			$exif = @exif_read_data( $file );
			$date = '';

			if ( ! empty( $exif[ $exif_field ] ) ) {
				$date = $exif[ $exif_field ];
			} elseif ( in_array( $exif_field, $date_fields, true ) ) {
				if ( ! empty( $exif['FileDateTime'] ) ) {
					$date = date( 'Y:m:d H:i:s', $exif['FileDateTime'] );
				} else {
					$date = get_the_date( 'Y:m:d H:i:s', $id );
				}
			}

			$attachments[] = [
				'ID'   => $id,
				'date' => $date,
			];
		}

		usort( $attachments, function( $a, $b ) use ( $order ) {
			$time_a = strtotime( $a['date'] );
			$time_b = strtotime( $b['date'] );
			return $order === 'ASC' ? $time_a - $time_b : $time_b - $time_a;
		} );

		return wp_list_pluck( $attachments, 'ID' );
	}
}
