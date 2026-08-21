import { describe, expect, it } from 'vitest';
import { safeMemberUrl } from '@/Pages/members.vue';

describe('member profile URLs', () => {
  it('keeps web and local links while rejecting executable schemes', () => {
    expect(safeMemberUrl('https://member.example/profile')).toBe('https://member.example/profile');
    expect(safeMemberUrl('/members/example')).toBe('/members/example');
    expect(safeMemberUrl('javascript:alert(1)')).toBe('');
    expect(safeMemberUrl('java\nscript:alert(1)')).toBe('');
    expect(safeMemberUrl('data:text/html,<script>alert(1)</script>')).toBe('');
  });
});
