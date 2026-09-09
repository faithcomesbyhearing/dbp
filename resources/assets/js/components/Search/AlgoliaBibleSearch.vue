<template>
	<div>
		<ais-index :appId="appId" :apiKey="apiKey" index-name="bibles_index">

			<div class="columns">
				<div class="column is-8 is-offset-2">
					<ais-search-box>
						<ais-input placeholder="Search product by name or reference..." :classNames="{'ais-input': 'input'}" />
					</ais-search-box>
					<div class="columns mt5 has-text-centered">
						<ais-stats class="column is-6 has-text-grey-light" />
						<span class="column is-6 has-text-grey-light">Powered By Algolia</span>
					</div>
				</div>
			</div>

			<div class="columns">
				<aside class="column is-3">
					<h5 class="mt10">Refine By Bucket ID</h5>
					<ais-refinement-list attribute-name="filesets.bucket_id" :classNames="{
						'ais-refinement-list__label': 'checkbox',
						'ais-refinement-list__checkbox': 'checkbox',
            		}"></ais-refinement-list>

					<h5 class="mt10">Set Type Code</h5>
					<ais-refinement-list attribute-name="filesets.set_type_code" :classNames="{
						'ais-refinement-list__label': 'checkbox',
						'ais-refinement-list__checkbox': 'checkbox',
            		}"></ais-refinement-list>

				</aside>
				<div class="column is-9">

					<ais-results class="columns is-multiline">
						<template slot-scope="{ result }">

							<div class="column is-6">
								<a v-bind:href=" '/wiki/bibles/' + result.abbr">
								<div class="box hit">

										<p class="title is-5"><ais-highlight :result="result" attribute-name="name"/></p>
										<p class="subtitle is-6"><ais-highlight v-if="result.vname" :result="result" attribute-name="vname"/></p>

									<time>{{result.date}}</time>
									<span class="iso">{{ result.script }} | {{ result.iso }}</span>
									{{ result.language }}
									{{ result.types }}
								</div>
								</a>
							</div>

						</template>
						<ais-powered-by />
					</ais-results>

					<ais-no-results/>

					<ais-pagination
							class="pagination"
							:classNames="{
              'ais-pagination': 'pagination',
              'ais-pagination__item': '',
              'ais-pagination__link': 'pagination-link',
              'ais-pagination__item--active': 'active-link',
              'ais-pagination__item--disabled': 'disabled'
            }"
							v-on:page-change="onPageChange"
					/>

				</div>
			</div>
		</ais-index>

	</div>
</template>

<script>
	export default {
		name: 'bible-search',
		props: {
			appId: {
				type: String,
				required: true,
			},
			apiKey: {
				type: String,
				required: true,
			},
		},
		methods: {
			onPageChange() {
				window.scrollTo(0, 0);
			},
		},
	};
</script>