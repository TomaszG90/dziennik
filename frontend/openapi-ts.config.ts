export default {
  input: '../shared/openapi.yaml',
  output: 'src/api/generated',
  plugins: ['@hey-api/typescript'],
};
