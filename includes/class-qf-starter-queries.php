<?php
/**
 * Starter query presets (seeded on activation into query_forge_saved_queries).
 *
 * @package Query_Forge
 */

namespace Query_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers four Free-tier starter graphs without overwriting user saves.
 */
final class QF_Starter_Queries {

	const OPTION_LIST_KEY = 'query_forge_saved_queries';

	/**
	 * Seed starter queries if their option keys are not already set.
	 */
	public static function seed_on_activation(): void {
		$starters = self::get_starter_definitions();
		$list     = get_option( self::OPTION_LIST_KEY, [] );
		if ( ! is_array( $list ) ) {
			$list = [];
		}

		foreach ( $starters as $query_id => $def ) {
			// Never overwrite an existing saved query at this key.
			if ( false !== get_option( $query_id, false ) ) {
				continue;
			}
			$payload = [
				'name'       => $def['name'],
				'date'       => $def['date'],
				'graphState' => wp_json_encode( $def['graph'] ),
				'logicJson'  => '',
			];
			update_option( $query_id, $payload, false );
			$list[ $query_id ] = [
				'id'   => $query_id,
				'name' => $def['name'],
				'date' => $def['date'],
			];
		}

		update_option( self::OPTION_LIST_KEY, $list, false );
	}

	/**
	 * @return array<string, array{name: string, date: string, graph: array}>
	 */
	private static function get_starter_definitions(): array {
		return [
			'query_forge_query_starter_your_recent_posts' => [
				'name' => 'Your Recent Posts',
				'date' => '2025-01-01T00:00:00.000Z',
				'graph' => [
					'nodes' => [
						[
							'id'       => 'source-1',
							'type'     => 'source',
							'data'     => [
								'sourceType' => 'post_type',
								'postType'   => 'page',
								'label'      => 'Source',
							],
							'position' => [ 'x' => 100, 'y' => 200 ],
						],
						[
							'id'       => 'filter-1',
							'type'     => 'filter',
							'data'     => [
								'field'     => 'post_date',
								'operator'  => '>=',
								'value'     => '',
								'valueType' => 'DATE',
								'label'     => 'Filter',
							],
							'position' => [ 'x' => 350, 'y' => 200 ],
						],
						[
							'id'       => 'target-1',
							'type'     => 'target',
							'data'     => [
								'postsPerPage'  => 10,
								'orderBy'       => 'date',
								'order'         => 'DESC',
								'cacheDuration' => 0,
								'label'         => 'Query Output',
							],
							'position' => [ 'x' => 600, 'y' => 200 ],
						],
					],
					'edges' => [
						[
							'id'     => 'reactflow__edge-source-1-filter-1',
							'source' => 'source-1',
							'target' => 'filter-1',
						],
						[
							'id'     => 'reactflow__edge-filter-1-target-1',
							'source' => 'filter-1',
							'target' => 'target-1',
						],
					],
				],
			],
			'query_forge_query_starter_adding_a_filter'   => [
				'name' => 'Adding A Filter',
				'date' => '2025-01-01T00:00:00.000Z',
				'graph' => [
					'nodes' => [
						[
							'id'       => 'source-1',
							'type'     => 'source',
							'data'     => [
								'sourceType' => 'post_type',
								'postType'   => 'post',
								'label'      => 'Source',
							],
							'position' => [ 'x' => 100, 'y' => 200 ],
						],
						[
							'id'       => 'filter-1',
							'type'     => 'filter',
							'data'     => [
								'field'     => '',
								'operator'  => '=',
								'value'     => '',
								'valueType' => 'CHAR',
								'label'     => 'Filter',
							],
							'position' => [ 'x' => 350, 'y' => 200 ],
						],
						[
							'id'       => 'target-1',
							'type'     => 'target',
							'data'     => [
								'postsPerPage'  => 10,
								'orderBy'       => 'date',
								'order'         => 'DESC',
								'cacheDuration' => 0,
								'label'         => 'Query Output',
							],
							'position' => [ 'x' => 600, 'y' => 200 ],
						],
					],
					'edges' => [
						[
							'id'     => 'reactflow__edge-source-1-filter-1',
							'source' => 'source-1',
							'target' => 'filter-1',
						],
						[
							'id'     => 'reactflow__edge-filter-1-target-1',
							'source' => 'filter-1',
							'target' => 'target-1',
						],
					],
				],
			],
			'query_forge_query_starter_union_idea'        => [
				'name' => 'The Union Idea',
				'date' => '2025-01-01T00:00:00.000Z',
				'graph' => [
					'nodes' => [
						[
							'id'       => 'source-1',
							'type'     => 'source',
							'data'     => [
								'sourceType' => 'post_type',
								'postType'   => 'post',
								'label'      => 'Source',
							],
							'position' => [ 'x' => 100, 'y' => 100 ],
						],
						[
							'id'       => 'filter-1',
							'type'     => 'filter',
							'data'     => [
								'field'     => '',
								'operator'  => '=',
								'value'     => '',
								'valueType' => 'CHAR',
								'label'     => 'Filter',
							],
							'position' => [ 'x' => 350, 'y' => 100 ],
						],
						[
							'id'       => 'filter-2',
							'type'     => 'filter',
							'data'     => [
								'field'     => '',
								'operator'  => '=',
								'value'     => '',
								'valueType' => 'CHAR',
								'label'     => 'Filter',
							],
							'position' => [ 'x' => 350, 'y' => 320 ],
						],
						[
							'id'       => 'logic-1',
							'type'     => 'logic',
							'data'     => [
								'relation' => 'OR',
								'label'    => 'Logic',
							],
							'position' => [ 'x' => 600, 'y' => 200 ],
						],
						[
							'id'       => 'target-1',
							'type'     => 'target',
							'data'     => [
								'postsPerPage'  => 10,
								'orderBy'       => 'date',
								'order'         => 'DESC',
								'cacheDuration' => 0,
								'label'         => 'Query Output',
							],
							'position' => [ 'x' => 850, 'y' => 200 ],
						],
					],
					'edges' => [
						[
							'id'     => 'reactflow__edge-source-1-filter-1',
							'source' => 'source-1',
							'target' => 'filter-1',
						],
						[
							'id'     => 'reactflow__edge-source-1-filter-2',
							'source' => 'source-1',
							'target' => 'filter-2',
						],
						[
							'id'           => 'reactflow__edge-filter-1-logic-1',
							'source'       => 'filter-1',
							'target'       => 'logic-1',
							'targetHandle' => 'input',
						],
						[
							'id'           => 'reactflow__edge-filter-2-logic-1',
							'source'       => 'filter-2',
							'target'       => 'logic-1',
							'targetHandle' => 'input',
						],
						[
							'id'     => 'reactflow__edge-logic-1-target-1',
							'source' => 'logic-1',
							'target' => 'target-1',
						],
					],
				],
			],
			'query_forge_query_starter_cpt_idea'          => [
				'name' => 'The CPT Idea',
				'date' => '2025-01-01T00:00:00.000Z',
				'graph' => [
					'nodes' => [
						[
							'id'       => 'source-1',
							'type'     => 'source',
							'data'     => [
								'sourceType' => 'cpts',
								'postType'   => '__first_cpt__',
								'label'      => 'Source',
							],
							'position' => [ 'x' => 100, 'y' => 200 ],
						],
						[
							'id'       => 'filter-1',
							'type'     => 'filter',
							'data'     => [
								'field'     => '',
								'operator'  => '=',
								'value'     => '',
								'valueType' => 'CHAR',
								'label'     => 'Filter',
							],
							'position' => [ 'x' => 350, 'y' => 200 ],
						],
						[
							'id'       => 'target-1',
							'type'     => 'target',
							'data'     => [
								'postsPerPage'  => 10,
								'orderBy'       => 'date',
								'order'         => 'DESC',
								'cacheDuration' => 0,
								'label'         => 'Query Output',
							],
							'position' => [ 'x' => 600, 'y' => 200 ],
						],
					],
					'edges' => [
						[
							'id'     => 'reactflow__edge-source-1-filter-1',
							'source' => 'source-1',
							'target' => 'filter-1',
						],
						[
							'id'     => 'reactflow__edge-filter-1-target-1',
							'source' => 'filter-1',
							'target' => 'target-1',
						],
					],
				],
			],
		];
	}
}
