import { getCurrentInstance } from 'vue';

export function useGlobal() {
  const app = getCurrentInstance();
  if (!app) throw new Error('No current Vue instance');

  return app.appContext.config.globalProperties;
}
