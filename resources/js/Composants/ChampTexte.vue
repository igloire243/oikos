<script setup>
import { computed } from 'vue';
import Champ from '@/Composants/Champ.vue';
import { icone as resoudreIcone } from '@/Composants/icones.js';

const props = defineProps({
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    erreur: { type: String, default: null },
    indication: { type: String, default: null },
    obligatoire: { type: Boolean, default: false },
    /** Un EXEMPLE de ce qu'on attend, pas une consigne — celle-ci va dans `indication`. */
    placeholder: { type: String, default: null },
    /** Un nom d'icône Lucide en kebab-case (voir Composants/icones.js). */
    icone: { type: String, default: null },
    id: { type: String, default: null },
});

defineEmits(['update:modelValue']);

const composantIcone = computed(() => (props.icone ? resoudreIcone(props.icone) : null));
</script>

<template>
    <Champ
        :label="label"
        :pour="id"
        :erreur="erreur"
        :indication="indication"
        :obligatoire="obligatoire"
    >
        <div class="relative">
            <component
                :is="composantIcone"
                v-if="composantIcone"
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                :stroke-width="1.8"
            />
            <input
                :id="id"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                class="w-full rounded-xl border-slate-300 text-sm shadow-sm transition placeholder:text-slate-400 focus:ring-2"
                :class="[
                    erreur ? 'border-rose-300 focus:border-rose-400' : 'focus:border-slate-400',
                    composantIcone ? 'pl-9' : '',
                ]"
                :style="{ '--tw-ring-color': 'var(--marque-200)' }"
                @input="$emit('update:modelValue', $event.target.value)"
            />
        </div>
    </Champ>
</template>
