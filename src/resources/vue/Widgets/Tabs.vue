<template>
    <ul class="nav nav-tabs" role="tablist">
        <li v-for="(tab, index) in tabs" :key="index" class="nav-item">
            <a role="tab" :aria-controls="tabKey(tab)" :aria-selected="!index" @click="tabClick"
               :class="'nav-link' + (!index ? ' active' : '')"
               :id="'tab-' + tabKey(tab)"
               :href="'#' + tabKey(tab)"
            >
                {{ $t(tabLabel(tab)) + (typeof tab != 'string' && 'count' in tab ? ` (${tab.count})` : '') }}
            </a>
        </li>
    </ul>
</template>

<script>
    import { Tab } from 'bootstrap'

    export default {
        props: {
            tabs: { type: Array, default: () => [] }
        },
        data() {
            return {
                clickHandlers: {}
            }
        },
        methods: {
            tabClick(event) {
                event.preventDefault()

                new Tab(event.target).show()

                const key = event.target.id.replace('tab-', '')

                if (key in this.clickHandlers) {
                    this.clickHandlers[key](event)
                }
            },
            tabKey(tab) {
                return this.tabLabel(tab).split(/[.-]/).slice(-1)
            },
            tabIndex(key) {
                return this.tabs.findIndex(tab => this.tabKey(tab) == key)
            },
            tabLabel(tab) {
                return typeof tab == 'string' ? tab : tab.label
            },
            updateCounter(key, count) {
                const index = this.tabIndex(key)
                let tab = this.tabs[index]

                if (typeof tab == 'string') {
                    tab = { label: tab, count }
                } else {
                    tab.count = count
                }

                this.$set(this.tabs, index, tab)
            },
            clickHandler(key, callback) {
                this.clickHandlers[key] = callback
            }
        }
    }
</script>
