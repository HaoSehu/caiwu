import { computed, ref } from "vue";
import { useWebsiteProductConfigurator } from "./useWebsiteProductConfigurator";
import { useWebsiteProductCheckout } from "./useWebsiteProductCheckout";
import { resolveHiddenConfigFieldSet } from "./configLinkage";

export function useWebsiteProductsPurchase() {
  const productDetail = ref(null);

  const configurator = useWebsiteProductConfigurator(productDetail);
  const checkout = useWebsiteProductCheckout({
    productDetail,
    configForm: configurator.configForm,
    pricingEntries: configurator.pricingEntries,
    buildConfigPayload: configurator.buildConfigPayload,
    initProductDefaults: configurator.initProductDefaults,
    resetConfigForm: configurator.resetConfigForm,
  });

  // M7 配置联动：visibility=false 的字段已被服务端从计价剔除，目录页配置区同步隐藏。
  // 无联动规则的商品 linkage 为 null，过滤集为空，列表原样透传（回归红线：零变化）。
  const hiddenConfigFields = computed(() =>
    resolveHiddenConfigFieldSet(checkout.quoteLinkage.value),
  );
  const visibleMachineConfigs = computed(() =>
    configurator.machineConfigs.value.filter(
      (cfg) => !hiddenConfigFields.value.has(cfg.key),
    ),
  );
  const visibleNetworkConfigs = computed(() =>
    configurator.networkConfigs.value.filter(
      (cfg) => !hiddenConfigFields.value.has(cfg.key),
    ),
  );
  const visibleOtherConfigs = computed(() =>
    configurator.otherConfigs.value.filter(
      (cfg) => !hiddenConfigFields.value.has(cfg.key),
    ),
  );
  const visibleSummaryItems = computed(() =>
    configurator.summaryItems.value.filter(
      (item) => !hiddenConfigFields.value.has(item.key),
    ),
  );

  return {
    ...configurator,
    machineConfigs: visibleMachineConfigs,
    networkConfigs: visibleNetworkConfigs,
    otherConfigs: visibleOtherConfigs,
    summaryItems: visibleSummaryItems,
    ...checkout,
  };
}
