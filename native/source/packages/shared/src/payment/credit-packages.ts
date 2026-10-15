export type CreditPackageId = number;

export type CreditPackage = {
  id: CreditPackageId;
  credits: number;
  price: number;
  nameKey: string;
  descriptionKey?: string;
  badgeKey?: string;
  popular?: boolean;
  featureKeys: string[];
};

export const creditPackages: CreditPackage[] = [
  // {
  //   id: 1,
  //   credits: 50,
  //   price: 3.99,
  //   nameKey: "checkout_topup_name",
  //   featureKeys: ["checkout_topup_feature_pages", "checkout_topup_feature_usd", "checkout_topup_feature_param"]
  // },
  {
    id: 2,
    credits: 100,
    price: 4.99,
    nameKey: "checkout_topup_name",
    featureKeys: ["checkout_topup_feature_pages", "checkout_topup_feature_usd", "checkout_topup_feature_param"]
  },
  {
    id: 3,
    credits: 500,
    price: 20,
    nameKey: "checkout_topup_name",
    popular: true,
    featureKeys: ["checkout_topup_feature_pages", "checkout_topup_feature_usd", "checkout_topup_feature_param"]
  },
  {
    id: 999,
    credits: 1,
    // PayTR rejects transactions below 1 TRY; this converts to more than 1 TRY.
    price: 0.1,
    nameKey: "checkout_test_package_name",
    featureKeys: ["checkout_test_package_feature"]
  }
];

export function getCreditPackageById(packageId: number): CreditPackage | undefined {
  return creditPackages.find((item) => item.id === packageId);
}
