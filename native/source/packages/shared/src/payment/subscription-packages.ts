export type SubscriptionPlanId = "starter" | "professional" | "enterprise";

export type SubscriptionPlan = {
  id: SubscriptionPlanId;
  nameKey: string;
  monthlyPrice: number | null;
  yearlyPrice: number | null;
  popular?: boolean;
  featureKeys: string[];
  credits: number;
};

export const subscriptionPlans: SubscriptionPlan[] = [
  {
    id: "starter",
    nameKey: "landing.pricing.plan_starter_name",
    monthlyPrice: 9.99,
    yearlyPrice: 7.99,
    credits: 100,
    featureKeys: [
      "landing.pricing.plan_starter_feature_1",
      "landing.pricing.plan_starter_feature_2",
      "landing.pricing.plan_starter_feature_3",
      "landing.pricing.plan_starter_feature_4",
      "landing.pricing.plan_starter_feature_5",
      "landing.pricing.plan_starter_feature_6"
    ]
  },
  {
    id: "professional",
    nameKey: "landing.pricing.plan_professional_name",
    monthlyPrice: 15.99,
    yearlyPrice: 11.99,
    credits: 250,
    popular: true,
    featureKeys: [
      "landing.pricing.plan_professional_feature_1",
      "landing.pricing.plan_professional_feature_2",
      "landing.pricing.plan_professional_feature_3",
      "landing.pricing.plan_professional_feature_4",
      "landing.pricing.plan_professional_feature_5",
      "landing.pricing.plan_professional_feature_6"
    ]
  },
  {
    id: "enterprise",
    nameKey: "landing.pricing.plan_enterprise_name",
    monthlyPrice: null,
    yearlyPrice: null,
    credits: 0,
    featureKeys: [
      "landing.pricing.plan_enterprise_feature_1",
      "landing.pricing.plan_enterprise_feature_2",
      "landing.pricing.plan_enterprise_feature_3",
      "landing.pricing.plan_enterprise_feature_4",
      "landing.pricing.plan_enterprise_feature_5"
    ]
  }
];

export function getSubscriptionPlanById(planId: string): SubscriptionPlan | undefined {
  return subscriptionPlans.find((item) => item.id === planId);
}
