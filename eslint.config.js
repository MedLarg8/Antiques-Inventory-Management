import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['resources/js/actions/**', 'resources/js/routes/**', 'resources/js/wayfinder/**', 'resources/js/components/ui/**'] },
    ...tseslint.configs.recommended,
);
