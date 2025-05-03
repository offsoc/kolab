<template>
    <div class="accordion" :id="id">
        <div v-for="(slot, slotName) in $slots" class="accordion-item position-relative" :key="slotName">
            <h2 :id="slotName + '-header'" class="accordion-header">
                <button type="button" data-bs-toggle="collapse" :data-bs-target="'#' + slotName"
                        :class="'accordion-button' + (isFirst(slotName) ? '' : ' collapsed')"
                        :aria-expanded="isFirst(slotName) ? 'true' : 'false'"
                        :aria-controls="slotName"
                >
                    {{ names[slotName] }}
                    <sup v-if="beta.includes(slotName)" class="badge bg-primary">{{ $t('dashboard.beta') }}</sup>
                </button>
                <div class="buttons">
                    <btn v-for="(button, idx) in buttons[slotName]" :key="idx" :icon="button.icon" class="btn-sm btn-outline-secondary" @click="button.click()">
                        {{ button.label }}
                    </btn>
                </div>
            </h2>
            <div :id="slotName" :class="'accordion-collapse collapse' + (isFirst(slotName) ? ' show' : '')" :data-bs-parent="'#' + id">
                <div class="accordion-body">
                    <slot :name="slotName"></slot>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            beta: { type: Array, default: () => [] },
            buttons: { type: Object, default: () => {} },
            id: { type: String, default: 'accordion' },
            names: { type: Object, default: () => {} },
        },
        methods: {
            isFirst(slotName) {
                for (let name in this.$slots) {
                    return name == slotName
                }
            }
        }
    }
</script>
