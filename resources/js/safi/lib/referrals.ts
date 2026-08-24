export type ReferralBranch = 'left' | 'right';

export function buildReferralBranchUrl(referralCode: string | null | undefined, branch: ReferralBranch, origin = window.location.origin) {
  const code = String(referralCode || '').trim();

  if (!code) {
    return '';
  }

  return `${origin}/register-ref-branch?ref=${encodeURIComponent(code)}&branch=${branch}`;
}
