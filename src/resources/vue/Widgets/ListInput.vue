<template>
    <div class="list-input" :id="id">
        <div class="input-group">
            <input :id="id + '-input'" type="text" class="form-control main-input" @keydown="keyDown">
            <a href="#" class="btn btn-outline-secondary" @click.prevent="addItem">
                <svg-icon icon="plus"></svg-icon>
                <span class="visually-hidden">{{ $t('btn.add') }}</span>
            </a>
        </div>
        <div class="input-group" v-for="(item, index) in list" :key="index">
            <input type="text" class="form-control" @input="$set(list, index, $event.target.value)" :value="item">
            <a href="#" class="btn btn-outline-secondary" @click.prevent="deleteItem(index)">
                <svg-icon icon="trash-can"></svg-icon>
                <span class="visually-hidden">{{ $t('btn.delete') }}</span>
            </a>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            list: { type: Array, default: () => [] },
            id: { type: String, default: '' }
        },
        mounted() {
            this.input = $(this.$el).find('.main-input')[0]

            // On form submit add the text from main input to the list
            // Users tend to forget about pressing the "plus" button
            // Note: We can't use form.onsubmit (too late)
            // Note: Use of input.onblur has been proven to be problematic
            // TODO: What with forms that have no submit button?
            $(this.$el).closest('form').find('button[type=submit]').on('click', () => {
                this.addItem(false)
            })
        },
        methods: {
            addItem(focus) {
                let value = this.input.value

                if (value) {
                    this.$set(this.list, this.list.length, value)

                    this.input.value = ''
                    this.input.classList.remove('is-invalid')

                    if (focus !== false) {
                        this.input.focus()
                    }

                    if (this.list.length == 1) {
                        this.$el.classList.remove('is-invalid')
                    }

                    this.$emit('change', this.$el)
                }
            },
            deleteItem(index) {
                this.$delete(this.list, index)
                this.$emit('change', this.$el)

                if (!this.list.length) {
                    this.$el.classList.remove('is-invalid')
                }
            },
            keyDown(e) {
                if (e.which == 13 && e.target.value) {
                    this.addItem()
                    e.preventDefault()
                }
            }
        }
    }
</script>
