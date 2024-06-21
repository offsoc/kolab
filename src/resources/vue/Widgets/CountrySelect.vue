<template>
    <div class="country-select">
        <a href="#" @click="$refs.dialog.show()">{{ $root.countriesText(value) }}</a>
        <modal-dialog class="fullscreen" ref="dialog" :title="$t('form.selectcountries')" @click="submit" :buttons="['save']">
            <div class="world-map"></div>
            <div class="tools m-2 rounded-pill">
                <btn icon="magnifying-glass-minus" class="button-zoom-out" disabled @click="zoomOut()"></btn>
                <btn icon="magnifying-glass-plus" class="button-zoom-in" @click="zoomIn()"></btn>
            </div>
            <div class="location m-2 p-2 rounded-pill">
                {{ $t('form.geolocation', { location: location || $t('form.unknown') }) }}
            </div>
        </modal-dialog>
    </div>
</template>

<script>
    import ModalDialog from './ModalDialog'
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faMagnifyingGlassPlus').definition,
        require('@fortawesome/free-solid-svg-icons/faMagnifyingGlassMinus').definition,
    )

    const SCALE_MAX = 4
    const SCALE_STEP = 0.5

    export default {
        components: {
            ModalDialog
        },
        props: {
            value: { type: Array, default: () => [] }
        },
        data() {
            return {
                country: '',
                location: '',
                scale: 1
            }
        },
        mounted() {
            this.$refs.dialog.events({
                show: () => {
                    let map = $(this.$el).find('.world-map')
                    if (!map.find('svg').length) {
                        // Load the svg map
                        axios.get(window.config['app.url'] + '/images/world.svg', { loader: [map, { small: false }] })
                            .then(response => {
                                if (response.data.startsWith('<svg')) {
                                    map.append(response.data)
                                    /*
                                        .on('wheel', event => {
                                            this[event.wheelDelta > 0 ? 'zoomIn' : 'zoomOut'](0.25)
                                        })
                                    */
                                }
                                this.initMap()
                            })

                        // Get the user current location
                        axios.get('/api/auth/location')
                            .then(response => {
                                if (this.country = response.data.countryCode) {
                                    this.location = window.config.countries[response.data.countryCode][1]
                                    map.find('svg [cc="' + this.country + '"]').attr({
                                            'data-location': 'true',
                                            'aria-selected': 'true'
                                    })
                                }
                            })
                    } else {
                        this.initMap()
                    }
                }
            })
        },
        methods: {
            initMap() {
                // Reset the map state, mark selected countries
                $(this.$el).find('[cc]').each((idx, element) => {
                    const country = $(element).attr('cc')
                    const countryData = window.config.countries[country]

                    $(element).attr({
                            'aria-label': countryData ? countryData[1] : '',
                            'aria-selected': country == this.country || this.value.includes(country) ? 'true' : 'false',
                            'data-location': country == this.country ? 'true' : null,
                            tabindex: 0,
                            role: 'option'
                        })
                        .off('click keypress')
                        .on('click keypress', event => {
                            // if user presses Enter, this selects/unselects the country
                            if (event.type == 'keypress' && event.which !== 13) {
                                // don't do anything if a different key is pressed
                                return
                            }

                            if (event.type == 'click') {
                                // remove the :active state from the element on click
                                $(element).get(0).blur()
                            }

                            if (country == this.country) {
                                // don't unselect the user location
                                return
                            }

                            const selected = $(element).attr('aria-selected') === 'true'

                            $(element).attr('aria-selected', selected ? 'false' : 'true')
                        })
                })
            },
            submit() {
                this.$refs.dialog.hide()

                let countries = []
                $(this.$el).find('[cc][aria-selected="true"]').each((idx, element) => {
                    countries.push($(element).attr('cc'))
                })

                this.$emit('input', countries)
            },
            updateZoomButtons() {
                $(this.$el).find('.button-zoom-out').prop('disabled', this.scale <= 1)
                $(this.$el).find('.button-zoom-in').prop('disabled', this.scale >= SCALE_MAX)
            },
            zoomIn(step = SCALE_STEP) {
                if (this.scale < SCALE_MAX) {
                    this.scale *= 1 + step
                    $(this.$el).find('.world-map svg').css('transform', `scale(${this.scale})`)
                }
                this.updateZoomButtons()
            },
            zoomOut(step = SCALE_STEP) {
                if (this.scale > 1) {
                    this.scale *= 1 / (1 + step)
                    $(this.$el).find('.world-map svg').css('transform', `scale(${this.scale})`)
                }
                this.updateZoomButtons()
            }
        }
    }
</script>
